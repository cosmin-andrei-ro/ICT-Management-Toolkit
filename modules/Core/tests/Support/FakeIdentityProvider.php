<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Tests\Support;

use Illuminate\Support\Facades\Http;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\HS256;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Modules\Core\Application\Sso\HostResolver;

/**
 * An OpenID Connect provider for tests: discovery, published keys and a token
 * endpoint answered through Http::fake, ID tokens signed with a real RSA key.
 */
final class FakeIdentityProvider
{
    public const string ISSUER = 'https://idp.example.test/realms/company';

    public JWK $key;

    private ?string $idToken = null;

    public function __construct()
    {
        $this->key = JWKFactory::createRSAKey(2048, ['kid' => 'key-1', 'alg' => 'RS256', 'use' => 'sig']);

        // The provider's host "resolves" to a public address.
        app()->instance(HostResolver::class, new class implements HostResolver
        {
            public function resolve(string $host): array
            {
                return ['93.184.216.34'];
            }
        });

        Http::fake([
            self::ISSUER.'/.well-known/openid-configuration' => Http::response([
                'issuer' => self::ISSUER,
                'authorization_endpoint' => self::ISSUER.'/protocol/openid-connect/auth',
                'token_endpoint' => self::ISSUER.'/protocol/openid-connect/token',
                'jwks_uri' => self::ISSUER.'/protocol/openid-connect/certs',
            ]),
            self::ISSUER.'/protocol/openid-connect/certs' => Http::response(['keys' => [$this->key->toPublic()->all()]]),
            self::ISSUER.'/protocol/openid-connect/token' => fn () => Http::response(['access_token' => 'access', 'token_type' => 'Bearer', 'id_token' => $this->idToken]),
        ]);
    }

    /**
     * The ID token the token endpoint answers next.
     *
     * @param  array<string, mixed>  $claims  Over the defaults (issuer, audience, times).
     */
    public function willAnswer(string $clientId, string $nonce, array $claims, ?JWK $key = null, string $algorithm = 'RS256'): void
    {
        $payload = [
            'iss' => self::ISSUER,
            'aud' => $clientId,
            'iat' => time(),
            'exp' => time() + 300,
            'nonce' => $nonce,
            ...$claims,
        ];
        $signingKey = $key ?? $this->key;
        $builder = new JWSBuilder(new AlgorithmManager([new RS256, new HS256]));
        $jws = $builder->create()->withPayload(json_encode($payload, JSON_THROW_ON_ERROR))
            ->addSignature($signingKey, ['alg' => $algorithm, 'kid' => $signingKey->has('kid') ? $signingKey->get('kid') : 'other'])->build();

        $this->idToken = (new CompactSerializer)->serialize($jws, 0);
    }
}
