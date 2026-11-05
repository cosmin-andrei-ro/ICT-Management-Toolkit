<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Application\Actions;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Validation\ValidationException;
use Modules\Core\Application\Authentication\ConfirmSecondFactor;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;
use Modules\Pam\Application\PamVault;
use Modules\Pam\Domain\Exceptions\VaultNotConfigured;
use Modules\Pam\Domain\Models\PrivilegedAccount;
use SensitiveParameter;

/**
 * Shows a credential to someone who gave a fresh second factor and a reason. Every
 * showing, and every wrong code, is in the audit trail. The caller checked the policy.
 */
final readonly class RevealSecret
{
    public function __construct(
        private ConfirmSecondFactor $secondFactor,
        private PamVault $vault,
        private AuditTrail $trail,
        private Config $config,
    ) {}

    /**
     * @throws ValidationException keyed to "code"
     * @throws VaultNotConfigured
     * @throws DecryptException With the wrong key.
     */
    public function handle(PrivilegedAccount $account, User $viewer, #[SensitiveParameter] string $code, string $reason): string
    {
        $this->assertSecondFactorHasSettled($viewer);
        $this->secondFactor->confirm($viewer, $code, 'pam_reveal', 'code');
        $ciphertext = $account->secret_ciphertext;

        if ($ciphertext === null) {
            throw ValidationException::withMessages(['code' => __('pam::accounts.errors.no_secret')]);
        }

        $secret = $this->vault->decrypt($account->id, $ciphertext);
        $this->trail->record(AuditEvent::SecretRevealed, $account, null, ['reason' => mb_substr($reason, 0, 500)], $viewer->id);

        return $secret;
    }

    /**
     * A stolen session could set up its own authenticator app and answer with it.
     * A new one must wait, while the person, who was told by email, can react.
     *
     * @throws ValidationException keyed to "code"
     */
    private function assertSecondFactorHasSettled(User $viewer): void
    {
        $hours = $this->config->get('pam.vault.second_factor_settle_hours');
        $confirmedAt = $viewer->two_factor_confirmed_at;

        if (is_int($hours) && $hours > 0 && $confirmedAt !== null && $confirmedAt->copy()->addHours($hours)->isFuture()) {
            throw ValidationException::withMessages(['code' => __('pam::accounts.errors.second_factor_too_new', ['hours' => $hours])]);
        }
    }
}
