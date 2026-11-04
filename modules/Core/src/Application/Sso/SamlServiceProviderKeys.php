<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Sso;

use Illuminate\Database\ConnectionInterface;
use Modules\Core\Domain\Sso\SsoProvider;
use Modules\Core\Domain\Tenancy\OrganizationScope;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * The toolkit's own key pair towards one identity provider (ADR 0071): it signs
 * logout messages and decrypts assertions. Made the first time it is needed,
 * under a row lock, so the certificate in the published metadata stays the one in use.
 */
final readonly class SamlServiceProviderKeys
{
    private const int DAYS_VALID = 3650;

    public function __construct(private ConnectionInterface $db) {}

    public function ensure(SsoProvider $provider): void
    {
        if ($provider->sp_certificate !== null && $provider->sp_private_key !== null) {
            return;
        }

        $this->db->transaction(function () use ($provider): void {
            $current = SsoProvider::query()->withoutGlobalScope(OrganizationScope::class)->whereKey($provider->id)->lockForUpdate()->firstOrFail();

            if ($current->sp_certificate === null || $current->sp_private_key === null) {
                [$certificate, $privateKey] = $this->generate();
                $current->forceFill(['sp_certificate' => $certificate, 'sp_private_key' => $privateKey])->saveQuietly();
            }

            $provider->forceFill(['sp_certificate' => $current->sp_certificate, 'sp_private_key' => $current->sp_private_key])->syncOriginal();
        });
    }

    /**
     * @return array{string, string} Certificate and private key, in PEM.
     */
    private function generate(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        if (! $key instanceof OpenSSLAsymmetricKey) {
            throw new RuntimeException('No RSA key.');
        }

        // openssl_csr_new takes the key by reference, which loses its type for the calls below
        $requestKey = $key;
        $request = openssl_csr_new(['commonName' => 'ICT Management Toolkit'], $requestKey, ['digest_alg' => 'sha256']);
        $signed = $request !== false && ! is_bool($request) ? openssl_csr_sign($request, null, $key, self::DAYS_VALID, ['digest_alg' => 'sha256']) : false;

        if ($signed === false || ! openssl_x509_export($signed, $certificate) || ! openssl_pkey_export($key, $privateKey) || ! is_string($certificate) || ! is_string($privateKey)) {
            throw new RuntimeException('No certificate.');
        }

        return [$certificate, $privateKey];
    }
}
