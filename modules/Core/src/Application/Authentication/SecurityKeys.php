<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Authentication;

use Cose\Algorithms;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\SecurityKey;
use Modules\Core\Domain\Models\User;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Security keys and passkeys (WebAuthn, ADR 0037). The browser proves it
 * holds the private key for this site: a page on another domain cannot use
 * it, which makes them resistant to phishing. The challenge of each ceremony
 * lives in the session and is used once.
 */
final readonly class SecurityKeys
{
    private const string CREATION = 'webauthn.creation';

    private const string REQUEST = 'webauthn.request';

    /** Milliseconds the browser waits for the person to use the key. */
    private const int TIMEOUT = 120000;

    private SerializerInterface $serializer;

    public function __construct(
        private Session $session,
        private Config $config,
        private ConnectionInterface $db,
        private AuditTrail $trail,
    ) {
        $this->serializer = (new WebauthnSerializerFactory(AttestationStatementSupportManager::create([new NoneAttestationStatementSupport])))->create();
    }

    public function has(User $user): bool
    {
        return SecurityKey::query()->where('user_id', $user->id)->exists();
    }

    /**
     * Options for the browser to create a key for the person (navigator.credentials.create).
     *
     * @return array<string, mixed>
     */
    public function creationOptions(User $user): array
    {
        $options = PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create($this->appName(), $this->rpId()),
            PublicKeyCredentialUserEntity::create($user->email, $user->id, $user->name),
            random_bytes(32),
            [PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_ES256), PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_RS256)],
            // Preferred, not required: a passkey when the device can keep one, a plain security key otherwise.
            AuthenticatorSelectionCriteria::create(null, AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED, AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED),
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            $this->descriptors($user),
            self::TIMEOUT,
        );

        return $this->remember(self::CREATION, $options);
    }

    /**
     * @throws InvalidArgumentException when the browser's answer does not match the challenge given to this person
     */
    public function register(User $user, string $credentialJson, string $name): SecurityKey
    {
        $options = $this->recall(self::CREATION, PublicKeyCredentialCreationOptions::class);
        $response = $this->credential($credentialJson)->response;

        if (! $options instanceof PublicKeyCredentialCreationOptions || $options->user->id !== $user->id || ! $response instanceof AuthenticatorAttestationResponse) {
            throw new InvalidArgumentException('No key is being added.');
        }

        try {
            $record = AuthenticatorAttestationResponseValidator::create($this->ceremonies()->creationCeremony())->check($response, $options, $this->rpId());
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('The key could not be verified.', previous: $exception);
        }

        $key = new SecurityKey;
        $key->forceFill([
            'user_id' => $user->id,
            'name' => mb_substr(trim($name) !== '' ? trim($name) : 'Security key', 0, 100),
            'credential_id' => self::base64Url($record->publicKeyCredentialId),
            'record' => $this->normalize($record),
            // Discoverable credentials ("passkeys") report the credProps extension or are backup eligible.
            'is_passkey' => $record->backupEligible === true || $this->isDiscoverable($credentialJson),
        ])->save();
        $this->trail->record(AuditEvent::SecurityKeyAdded, $user, null, ['key' => $key->name], $user->id);

        return $key;
    }

    /**
     * Options for the browser to use a key (navigator.credentials.get): one of
     * the person's keys as a second factor, or any passkey to sign in without
     * a password (person null).
     *
     * @return array<string, mixed>
     */
    public function requestOptions(?User $user): array
    {
        $options = PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            $this->rpId(),
            $user !== null ? $this->descriptors($user) : [],
            // Alone, a passkey must prove who holds it (PIN, fingerprint): it replaces both factors.
            $user !== null ? PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED : PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            self::TIMEOUT,
        );

        return $this->remember(self::REQUEST, $options);
    }

    /**
     * @param  ?User  $user  The person whose second factor this is; null for a passkey sign-in.
     * @return User Whose key it is.
     *
     * @throws InvalidArgumentException when the key is unknown, someone else's, or its answer is not valid
     */
    public function authenticate(?User $user, string $credentialJson): User
    {
        $options = $this->recall(self::REQUEST, PublicKeyCredentialRequestOptions::class);
        $credential = $this->credential($credentialJson);
        $response = $credential->response;

        if (! $options instanceof PublicKeyCredentialRequestOptions || ! $response instanceof AuthenticatorAssertionResponse || ($user === null && $options->allowCredentials !== [])) {
            throw new InvalidArgumentException('No sign-in is in progress.');
        }

        return $this->db->transaction(function () use ($user, $credential, $response, $options): User {
            // Locked: the signature counter must not move backwards under two requests.
            $key = SecurityKey::query()->with('user')->where('credential_id', self::base64Url($credential->rawId))->lockForUpdate()->first();

            if ($key === null || ($user !== null && $key->user_id !== $user->id)) {
                throw new InvalidArgumentException('Unknown key.');
            }

            if ($user === null && ($response->userHandle === null || $response->userHandle !== $key->user_id || ! $key->user->is_active || ! $key->user->canSignIn())) {
                throw new InvalidArgumentException('This passkey cannot sign in.');
            }

            try {
                $record = AuthenticatorAssertionResponseValidator::create($this->ceremonies()->requestCeremony())
                    ->check($this->record($key), $response, $options, $this->rpId(), $key->user_id);
            } catch (Throwable $exception) {
                throw new InvalidArgumentException('The key could not be verified.', previous: $exception);
            }

            $key->forceFill(['record' => $this->normalize($record), 'last_used_at' => now()])->save();

            return $key->user;
        });
    }

    public function remove(SecurityKey $key, User $actor): void
    {
        $key->delete();
        $this->trail->record(AuditEvent::SecurityKeyRemoved, $key->user, null, ['key' => $key->name], $actor->id);
    }

    /**
     * When two-factor authentication is turned off or reset, the keys go with it.
     */
    public function removeAll(User $user, User $actor): void
    {
        foreach (SecurityKey::query()->with('user')->where('user_id', $user->id)->get() as $key) {
            $this->remove($key, $actor);
        }
    }

    /**
     * @return list<PublicKeyCredentialDescriptor>
     */
    private function descriptors(User $user): array
    {
        return array_values(SecurityKey::query()->where('user_id', $user->id)->get()
            ->map(fn (SecurityKey $key): PublicKeyCredentialDescriptor => $this->record($key)->getPublicKeyCredentialDescriptor())
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function remember(string $slot, PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options): array
    {
        $json = $this->serializer->serialize($options, 'json', [AbstractObjectNormalizer::SKIP_NULL_VALUES => true]);
        $this->session->put($slot, $json);

        return self::object($json);
    }

    /**
     * The options of the ceremony in progress, forgotten as soon as read: a challenge is used once.
     *
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return ?T
     */
    private function recall(string $slot, string $class): ?object
    {
        $json = $this->session->pull($slot);

        if (! is_string($json)) {
            return null;
        }

        $options = $this->serializer->deserialize($json, $class, 'json');

        return $options instanceof $class ? $options : null;
    }

    private function credential(string $json): PublicKeyCredential
    {
        try {
            return $this->serializer->deserialize($json, PublicKeyCredential::class, 'json');
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Not a key answer.', previous: $exception);
        }
    }

    private function record(SecurityKey $key): CredentialRecord
    {
        return $this->serializer->deserialize(json_encode($key->record, JSON_THROW_ON_ERROR), CredentialRecord::class, 'json');
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(CredentialRecord $record): array
    {
        return self::object($this->serializer->serialize($record, 'json'));
    }

    private function isDiscoverable(string $credentialJson): bool
    {
        $extensions = self::object($credentialJson)['clientExtensionResults'] ?? null;
        $properties = is_array($extensions) ? ($extensions['credProps'] ?? null) : null;

        return is_array($properties) && ($properties['rk'] ?? false) === true;
    }

    private function ceremonies(): CeremonyStepManagerFactory
    {
        $factory = new CeremonyStepManagerFactory;
        $factory->setAllowedOrigins([$this->origin()]);

        return $factory;
    }

    /**
     * The relying party is this installation's host (APP_URL): keys made for it work nowhere else.
     */
    private function rpId(): string
    {
        $host = parse_url($this->config->string('app.url'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'localhost';
    }

    private function origin(): string
    {
        $parts = parse_url($this->config->string('app.url'));
        $port = is_array($parts) && isset($parts['port']) ? ':'.$parts['port'] : '';

        return (is_array($parts) && isset($parts['scheme']) ? $parts['scheme'] : 'https').'://'.$this->rpId().$port;
    }

    /**
     * @return array<string, mixed>
     */
    private static function object(string $json): array
    {
        $decoded = json_decode($json, true, 32);
        $object = [];

        foreach (is_array($decoded) ? $decoded : [] as $key => $value) {
            if (is_string($key)) {
                $object[$key] = $value;
            }
        }

        return $object;
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function appName(): string
    {
        $name = $this->config->get('app.name');

        return is_string($name) && $name !== '' ? $name : 'ICT Management Toolkit';
    }
}
