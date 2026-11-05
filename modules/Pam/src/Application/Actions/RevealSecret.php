<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Application\Actions;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;
use Modules\Pam\Application\PamVault;
use Modules\Pam\Application\VaultSecondFactor;
use Modules\Pam\Domain\Enums\RequestStatus;
use Modules\Pam\Domain\Exceptions\VaultNotConfigured;
use Modules\Pam\Domain\Models\AccessRequest;
use Modules\Pam\Domain\Models\PrivilegedAccount;
use SensitiveParameter;

/**
 * Shows a credential to someone who gave a fresh second factor and a reason. Every
 * showing, and every wrong code, is in the audit trail. The caller checked the policy.
 */
final readonly class RevealSecret
{
    public function __construct(
        private VaultSecondFactor $secondFactor,
        private PamVault $vault,
        private AuditTrail $trail,
    ) {}

    /**
     * @param  string  $reason  Ignored for an account that needs approval: the approved request gave it.
     *
     * @throws ValidationException keyed to "code"
     * @throws VaultNotConfigured
     * @throws DecryptException With the wrong key.
     */
    public function handle(PrivilegedAccount $account, User $viewer, #[SensitiveParameter] string $code, string $reason): string
    {
        $request = $this->activeApproval($account, $viewer);
        $this->secondFactor->confirm($viewer, $code, 'pam_reveal', 'code');
        $ciphertext = $account->secret_ciphertext;

        if ($ciphertext === null) {
            throw ValidationException::withMessages(['code' => __('pam::accounts.errors.no_secret')]);
        }

        $secret = $this->vault->decrypt($account->id, $ciphertext);
        $this->trail->record(AuditEvent::SecretRevealed, $account, null, ['reason' => mb_substr($request->reason ?? $reason, 0, 500), 'request' => $request?->id], $viewer->id);

        return $secret;
    }

    /**
     * @throws ValidationException keyed to "code"
     */
    private function activeApproval(PrivilegedAccount $account, User $viewer): ?AccessRequest
    {
        if (! $account->requires_approval) {
            return null;
        }

        $request = AccessRequest::query()->where('account_id', $account->id)->where('user_id', $viewer->id)->where('status', RequestStatus::Approved)
            ->where('expires_at', '>', now())->latest('decided_at')->first();

        return $request ?? throw ValidationException::withMessages(['code' => __('pam::accounts.errors.approval_needed')]);
    }
}
