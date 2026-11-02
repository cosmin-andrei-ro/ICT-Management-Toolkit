<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Tests\Support;

use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\TextStringObject;
use CBOR\UnsignedIntegerObject;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * A security key in software, for tests: a real P-256 key pair answering the
 * browser's WebAuthn calls as an authenticator would (attestation "none",
 * ECDSA signatures), so the server checks real signatures.
 */
final class VirtualAuthenticator
{
    public string $credentialId;

    private OpenSSLAsymmetricKey $key;

    private int $counter = 0;

    public function __construct(
        private readonly string $origin = 'http://localhost',
        private readonly string $rpId = 'localhost',
    ) {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $this->key = $key instanceof OpenSSLAsymmetricKey ? $key : throw new RuntimeException('No EC key.');
        $this->credentialId = random_bytes(16);
    }

    /**
     * The answer to navigator.credentials.create().
     *
     * @param  array<string, mixed>  $options  As SecurityKeys::creationOptions() returns them.
     */
    public function create(array $options, bool $discoverable = true, ?string $origin = null): string
    {
        $details = openssl_pkey_get_details($this->key);
        $ec = is_array($details) && is_array($details['ec'] ?? null) ? $details['ec'] : throw new RuntimeException('No EC details.');
        $coordinate = fn (mixed $value): string => str_pad(is_string($value) ? $value : '', 32, "\0", STR_PAD_LEFT);
        $publicKey = MapObject::create()
            ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))
            ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(-7))
            ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))
            ->add(NegativeIntegerObject::create(-2), ByteStringObject::create($coordinate($ec['x'] ?? null)))
            ->add(NegativeIntegerObject::create(-3), ByteStringObject::create($coordinate($ec['y'] ?? null)));
        // User present, user verified, attested credential data included.
        $authenticatorData = hash('sha256', $this->rpId, true).chr(0x01 | 0x04 | 0x40).pack('N', $this->counter)
            .str_repeat("\0", 16).pack('n', strlen($this->credentialId)).$this->credentialId.$publicKey;
        $attestation = MapObject::create()
            ->add(TextStringObject::create('fmt'), TextStringObject::create('none'))
            ->add(TextStringObject::create('attStmt'), MapObject::create())
            ->add(TextStringObject::create('authData'), ByteStringObject::create($authenticatorData));

        return self::json([
            'id' => self::base64Url($this->credentialId),
            'rawId' => self::base64Url($this->credentialId),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => self::base64Url($this->clientData('webauthn.create', $options, $origin)),
                'attestationObject' => self::base64Url((string) $attestation),
                'transports' => ['usb'],
            ],
            'clientExtensionResults' => $discoverable ? ['credProps' => ['rk' => true]] : [],
        ]);
    }

    /**
     * The answer to navigator.credentials.get().
     *
     * @param  array<string, mixed>  $options  As SecurityKeys::requestOptions() returns them.
     */
    public function get(array $options, string $userHandle, bool $verified = true, ?string $origin = null): string
    {
        $this->counter++;
        $clientData = $this->clientData('webauthn.get', $options, $origin);
        $authenticatorData = hash('sha256', $this->rpId, true).chr(0x01 | ($verified ? 0x04 : 0)).pack('N', $this->counter);
        openssl_sign($authenticatorData.hash('sha256', $clientData, true), $signature, $this->key, OPENSSL_ALGO_SHA256);

        return self::json([
            'id' => self::base64Url($this->credentialId),
            'rawId' => self::base64Url($this->credentialId),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => self::base64Url($clientData),
                'authenticatorData' => self::base64Url($authenticatorData),
                'signature' => self::base64Url(is_string($signature) ? $signature : ''),
                'userHandle' => self::base64Url($userHandle),
            ],
            'clientExtensionResults' => [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function clientData(string $type, array $options, ?string $origin): string
    {
        return self::json(['type' => $type, 'challenge' => $options['challenge'] ?? '', 'origin' => $origin ?? $this->origin, 'crossOrigin' => false]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
