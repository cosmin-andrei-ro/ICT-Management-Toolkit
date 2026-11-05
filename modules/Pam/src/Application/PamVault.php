<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Application;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Modules\Pam\Domain\Exceptions\VaultNotConfigured;
use SensitiveParameter;

/**
 * Encrypts credentials with the vault's own key (ADR 0078), so that the application
 * key alone does not open them. The only place their plain text exists.
 */
final readonly class PamVault
{
    private const string CIPHER = 'AES-256-GCM';

    public function __construct(private Config $config) {}

    public function isConfigured(): bool
    {
        return $this->key() !== null;
    }

    /**
     * Sealed to its account: a ciphertext copied onto another row does not open.
     *
     * @throws VaultNotConfigured
     */
    public function encrypt(string $accountId, #[SensitiveParameter] string $secret): string
    {
        return $this->encrypter()->encryptString($accountId."\0".$secret);
    }

    /**
     * @throws VaultNotConfigured
     * @throws DecryptException With the wrong key, or when it belongs to another account.
     */
    public function decrypt(string $accountId, string $ciphertext): string
    {
        $plain = $this->encrypter()->decryptString($ciphertext);
        $prefix = $accountId."\0";

        if (! str_starts_with($plain, $prefix)) {
            throw new DecryptException('This credential belongs to another account.');
        }

        return substr($plain, strlen($prefix));
    }

    public static function generateKey(): string
    {
        return 'base64:'.base64_encode(Encrypter::generateKey(self::CIPHER));
    }

    private function encrypter(): Encrypter
    {
        $key = $this->key() ?? throw new VaultNotConfigured;
        $previous = $this->config->get('pam.vault.previous_keys', []);
        $previousKeys = [];

        foreach (is_array($previous) ? $previous : [] as $old) {
            $decoded = is_string($old) ? self::decode($old) : null;

            if ($decoded !== null) {
                $previousKeys[] = $decoded;
            }
        }

        return (new Encrypter($key, self::CIPHER))->previousKeys($previousKeys);
    }

    /**
     * A vault key equal to the application key would defeat its purpose: it counts as none.
     */
    private function key(): ?string
    {
        $configured = $this->config->get('pam.vault.key');
        $key = is_string($configured) ? self::decode($configured) : null;
        $application = $this->config->get('app.key');

        if ($key === null || (is_string($application) && self::decode($application) === $key)) {
            return null;
        }

        return $key;
    }

    private static function decode(string $value): ?string
    {
        $decoded = base64_decode(str_starts_with($value, 'base64:') ? substr($value, 7) : '', true);

        return is_string($decoded) && Encrypter::supported($decoded, self::CIPHER) ? $decoded : null;
    }
}
