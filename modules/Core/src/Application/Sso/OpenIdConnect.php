<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Sso;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWKSet;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Algorithm\ES384;
use Jose\Component\Signature\Algorithm\PS256;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\Algorithm\RS384;
use Jose\Component\Signature\Algorithm\RS512;
use Jose\Component\Signature\JWS;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Modules\Core\Domain\Sso\SsoProvider;
use Throwable;

/**
 * OpenID Connect sign-in (ADR 0038): authorization code flow with PKCE,
 * state and nonce, and the ID token checked here - signature against the
 * provider's published keys, issuer, audience, expiry and nonce. Only
 * asymmetric signature algorithms are accepted: never "none" or an HMAC
 * keyed with a public value.
 */
final readonly class OpenIdConnect
{
    private const string PENDING = 'sso.pending';

    /** Seconds the provider has to send the person back. */
    private const int PENDING_SECONDS = 600;

    /** Seconds of clock difference tolerated with the provider. */
    private const int LEEWAY = 60;

    private const int TIMEOUT = 10;

    public function __construct(
        private Http $http,
        private Cache $cache,
        private Session $session,
        private OutboundUrlGuard $guard,
    ) {}

    /**
     * Where to send the browser to sign in at the provider.
     *
     * @throws SsoFailed
     */
    public function authorizationUrl(SsoProvider $provider, string $redirectUri): string
    {
        $configuration = $this->configuration($provider);
        $state = self::random(32);
        $nonce = self::random(32);
        $verifier = self::random(48);

        $this->session->put(self::PENDING, [
            'provider_id' => $provider->id,
            'state' => $state,
            'nonce' => $nonce,
            'verifier' => $verifier,
            'redirect_uri' => $redirectUri,
            'expires_at' => now()->getTimestamp() + self::PENDING_SECONDS,
        ]);

        return $configuration['authorization_endpoint'].(str_contains($configuration['authorization_endpoint'], '?') ? '&' : '?').http_build_query([
            'response_type' => 'code',
            'client_id' => $provider->client_id,
            'redirect_uri' => $redirectUri,
            'scope' => $provider->scopes,
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => self::base64Url(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
        ]);
    }

    /**
     * The claims of the person who just signed in at the provider.
     *
     * @return array<string, mixed>
     *
     * @throws SsoFailed
     */
    public function claims(SsoProvider $provider, string $state, string $code): array
    {
        $pending = $this->session->pull(self::PENDING);

        if (! is_array($pending) || ($pending['provider_id'] ?? null) !== $provider->id || ! is_string($pending['state'] ?? null)
            || ! hash_equals($pending['state'], $state) || ! is_int($pending['expires_at'] ?? null) || $pending['expires_at'] < now()->getTimestamp()) {
            throw SsoFailed::because('state');
        }

        $configuration = $this->configuration($provider);

        try {
            $response = $this->pinnedTo($configuration['token_endpoint'])->asForm()->acceptJson()->timeout(self::TIMEOUT)->withoutRedirecting()->post($configuration['token_endpoint'], [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $pending['redirect_uri'] ?? '',
                'client_id' => $provider->client_id,
                'client_secret' => $provider->client_secret,
                'code_verifier' => $pending['verifier'] ?? '',
            ]);
        } catch (Throwable) {
            throw SsoFailed::because('token_endpoint');
        }

        $idToken = $response->successful() ? $response->json('id_token') : null;

        if (! is_string($idToken)) {
            throw SsoFailed::because('token_endpoint');
        }

        return $this->verify($idToken, $provider, $configuration, is_string($pending['nonce'] ?? null) ? $pending['nonce'] : '');
    }

    /**
     * @param  array{issuer: string, authorization_endpoint: string, token_endpoint: string, jwks_uri: string}  $configuration
     * @return array<string, mixed>
     *
     * @throws SsoFailed
     */
    private function verify(string $idToken, SsoProvider $provider, array $configuration, string $nonce): array
    {
        try {
            $jws = (new CompactSerializer)->unserialize($idToken);
        } catch (Throwable) {
            throw SsoFailed::because('id_token');
        }

        $verifier = new JWSVerifier(new AlgorithmManager([new RS256, new RS384, new RS512, new PS256, new ES256, new ES384]));
        $algorithm = $jws->getSignature(0)->getProtectedHeaderParameter('alg');

        if (! is_string($algorithm) || ! $verifier->getSignatureAlgorithmManager()->has($algorithm)) {
            throw SsoFailed::because('algorithm');
        }

        // Keys rotate: when no cached key matches, the provider's current set is fetched once.
        if (! $this->verifies($verifier, $jws, $this->keys($configuration['jwks_uri'], false)) && ! $this->verifies($verifier, $jws, $this->keys($configuration['jwks_uri'], true))) {
            throw SsoFailed::because('signature');
        }

        $claims = json_decode((string) $jws->getPayload(), true, 32);

        if (! is_array($claims)) {
            throw SsoFailed::because('id_token');
        }

        $audience = $claims['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];
        $now = now()->getTimestamp();

        $valid = ($claims['iss'] ?? null) === $configuration['issuer']
            && in_array($provider->client_id, $audiences, true)
            && (count($audiences) === 1 || ($claims['azp'] ?? null) === $provider->client_id)
            && is_int($claims['exp'] ?? null) && $now <= $claims['exp'] + self::LEEWAY
            && (! isset($claims['iat']) || (is_int($claims['iat']) && $now >= $claims['iat'] - self::LEEWAY))
            && (! isset($claims['nbf']) || (is_int($claims['nbf']) && $now >= $claims['nbf'] - self::LEEWAY))
            && is_string($claims['nonce'] ?? null) && $nonce !== '' && hash_equals($nonce, $claims['nonce'])
            && is_string($claims['sub'] ?? null) && $claims['sub'] !== '';

        if (! $valid) {
            throw SsoFailed::because('claims');
        }

        $result = [];

        foreach ($claims as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function verifies(JWSVerifier $verifier, JWS $jws, JWKSet $keys): bool
    {
        try {
            return $keys->count() > 0 && $verifier->verifyWithKeySet($jws, $keys, 0);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The provider's discovery document, cached for an hour.
     *
     * @return array{issuer: string, authorization_endpoint: string, token_endpoint: string, jwks_uri: string}
     *
     * @throws SsoFailed
     */
    private function configuration(SsoProvider $provider): array
    {
        $issuer = rtrim($provider->issuer, '/');

        if (! $this->guard->allows($issuer)) {
            throw SsoFailed::because('issuer_not_allowed');
        }

        $document = $this->cached('sso.discovery.'.sha1($issuer), $issuer.'/.well-known/openid-configuration');
        $configuration = [];

        foreach (['issuer', 'authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $key) {
            $value = is_array($document) ? ($document[$key] ?? null) : null;

            if (! is_string($value) || ($key !== 'issuer' && ! $this->guard->allows($value))) {
                throw SsoFailed::because('discovery');
            }

            $configuration[$key] = $value;
        }

        // The document must describe the issuer configured, not another one.
        if (rtrim($configuration['issuer'], '/') !== $issuer) {
            throw SsoFailed::because('discovery');
        }

        return $configuration;
    }

    private function keys(string $uri, bool $fresh): JWKSet
    {
        $key = 'sso.jwks.'.sha1($uri);

        if ($fresh) {
            $this->cache->forget($key);
        }

        $document = $this->cached($key, $uri);

        try {
            return is_array($document) ? JWKSet::createFromKeyData($document) : new JWKSet([]);
        } catch (Throwable) {
            return new JWKSet([]);
        }
    }

    /**
     * A JSON document of the provider, kept an hour; failures are not kept.
     *
     * @return ?array<mixed>
     */
    private function cached(string $key, string $url): ?array
    {
        $document = $this->cache->get($key);

        if (is_array($document)) {
            return $document;
        }

        $document = $this->fetchJson($url);

        if ($document !== null) {
            $this->cache->put($key, $document, 3600);
        }

        return $document;
    }

    /**
     * @return ?array<mixed>
     */
    private function fetchJson(string $url): ?array
    {
        try {
            $response = $this->pinnedTo($url)->acceptJson()->timeout(self::TIMEOUT)->withoutRedirecting()->get($url);
        } catch (Throwable) {
            return null;
        }

        $json = $response->successful() ? $response->json() : null;

        return is_array($json) ? $json : null;
    }

    /**
     * A client that connects to the address just checked, not to a fresh DNS answer:
     * the discovery document's URLs come from the provider, and the flow is started
     * by anonymous visitors.
     *
     * @throws SsoFailed
     */
    private function pinnedTo(string $url): PendingRequest
    {
        $target = $this->guard->target($url) ?? throw SsoFailed::because('issuer_not_allowed');
        $address = str_contains($target['address'], ':') ? '['.$target['address'].']' : $target['address'];

        return $this->http->withOptions(['curl' => [CURLOPT_RESOLVE => [$target['host'].':'.$target['port'].':'.$address]]]);
    }

    /**
     * @param  positive-int  $bytes
     */
    private static function random(int $bytes): string
    {
        return self::base64Url(random_bytes($bytes));
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
