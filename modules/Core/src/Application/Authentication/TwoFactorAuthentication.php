<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Authentication;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\User;
use PragmaRX\Google2FAQRCode\Google2FA;
use SensitiveParameter;

/**
 * Authenticator-app codes (TOTP, RFC 6238) and single-use recovery codes.
 * Owner's decision: required for everyone holding a role other than Requester,
 * in any organization, AD accounts included; optional for the others.
 */
final readonly class TwoFactorAuthentication
{
    public const int RECOVERY_CODES = 10;

    /** Accepts the previous and the next 30-second code too: phone clocks drift. */
    private const int WINDOW = 1;

    public function __construct(
        private Google2FA $google2fa,
        private ConnectionInterface $db,
        private AuditTrail $trail,
        private Config $config,
    ) {}

    public function isEnabled(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null && $user->two_factor_secret !== null;
    }

    public function isRequiredFor(User $user): bool
    {
        $tables = $this->config->get('permission.table_names');
        $modelRoles = is_array($tables) && is_string($tables['model_has_roles'] ?? null) ? $tables['model_has_roles'] : 'model_has_roles';
        $roles = is_array($tables) && is_string($tables['roles'] ?? null) ? $tables['roles'] : 'roles';

        // Across all organizations (teams), not only the current one.
        return $this->db->table($modelRoles)
            ->join($roles, "{$roles}.id", '=', "{$modelRoles}.role_id")
            ->where("{$modelRoles}.model_id", $user->id)
            ->where("{$modelRoles}.model_type", $user->getMorphClass())
            ->where("{$roles}.name", '!=', DefaultRole::Requester->value)
            ->exists();
    }

    public function newSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /**
     * The QR code to scan, as an SVG data URL.
     */
    public function qrCode(User $user, #[SensitiveParameter] string $secret): string
    {
        $issuer = $this->config->get('app.name');

        return $this->google2fa->getQRCodeInline(is_string($issuer) ? $issuer : 'ICT Management Toolkit', $user->email, $secret);
    }

    /**
     * Turns two-factor authentication on once the user proved their app works.
     *
     * @return list<string>|null The recovery codes, to show once; null when the code is wrong.
     */
    public function enable(User $user, #[SensitiveParameter] string $secret, #[SensitiveParameter] string $code): ?array
    {
        // With a previous step (0 = none yet) the library returns the matched time step.
        $step = $this->google2fa->verifyKeyNewer($secret, self::digits($code), 0, self::WINDOW);

        if (! is_int($step)) {
            return null;
        }

        $codes = self::newRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => array_map(self::hash(...), $codes),
            'two_factor_last_used_step' => $step,
        ])->save();

        $this->trail->record(AuditEvent::MfaEnabled, $user, null, null, $user->id);

        return $codes;
    }

    /**
     * An app code, accepted once, or an unused recovery code, consumed.
     */
    public function verify(User $user, #[SensitiveParameter] string $code): bool
    {
        if (! $this->isEnabled($user)) {
            return false;
        }

        return $this->db->transaction(function () use ($user, $code): bool {
            // Locked: two requests with the same code must not both pass.
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $digits = self::digits($code);

            if (strlen($digits) === 6) {
                $step = $this->google2fa->verifyKeyNewer((string) $locked->two_factor_secret, $digits, $locked->two_factor_last_used_step ?? 0, self::WINDOW);

                if (is_int($step)) {
                    $locked->forceFill(['two_factor_last_used_step' => $step])->save();
                    $user->setRawAttributes($locked->getAttributes(), true);

                    return true;
                }
            }

            $hashes = $locked->two_factor_recovery_codes ?? [];
            $index = array_search(self::hash($code), $hashes, true);

            if ($index === false) {
                return false;
            }

            unset($hashes[$index]);
            $locked->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();
            $user->setRawAttributes($locked->getAttributes(), true);
            $this->trail->record(AuditEvent::MfaRecoveryCodeUsed, $locked, null, ['remaining' => count($hashes)], $locked->id);

            return true;
        });
    }

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = self::newRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => array_map(self::hash(...), $codes)])->save();
        $this->trail->record(AuditEvent::MfaRecoveryCodesRegenerated, $user, null, null, $user->id);

        return $codes;
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    /**
     * Turns it off: by the user themselves, or reset by an administrator for
     * someone who lost their phone (they set it up again at next sign-in).
     */
    public function disable(User $user, ?User $actor = null): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_last_used_step' => null,
        ])->save();

        $resetByOther = $actor !== null && $actor->id !== $user->id;
        $this->trail->record($resetByOther ? AuditEvent::MfaReset : AuditEvent::MfaDisabled, $user, null, null, $actor->id ?? $user->id);
    }

    /**
     * @return list<string>
     */
    private static function newRecoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            // 50 random bits each, from an alphabet without look-alike characters.
            $codes[] = self::randomChunk(5).'-'.self::randomChunk(5);
        }

        return $codes;
    }

    private static function randomChunk(int $length): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $chunk = '';

        for ($i = 0; $i < $length; $i++) {
            $chunk .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $chunk;
    }

    private static function hash(#[SensitiveParameter] string $code): string
    {
        return hash('sha256', strtolower(trim($code)));
    }

    private static function digits(#[SensitiveParameter] string $code): string
    {
        return (string) preg_replace('/\D/', '', $code);
    }
}
