<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Infrastructure\Console;

use Illuminate\Console\Command;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Tenancy\OrganizationScope;
use Modules\Pam\Application\PamVault;
use Modules\Pam\Domain\Models\PrivilegedAccount;
use Throwable;

/**
 * Moves every credential to the current PAM_KEY, after setting the old one in
 * PAM_PREVIOUS_KEYS. Safe to run again. A credential it cannot read is reported
 * and left alone, so that a wrong key loses nothing.
 */
final class RekeyVaultCommand extends Command
{
    protected $signature = 'pam:rekey';

    protected $description = 'Re-encrypt privileged credentials with the current vault key';

    public function handle(PamVault $vault, AuditTrail $trail): int
    {
        if (! $vault->isConfigured()) {
            $this->error(__('pam::accounts.errors.not_configured'));

            return self::FAILURE;
        }

        $moved = 0;
        $failed = 0;

        PrivilegedAccount::query()->withoutGlobalScope(OrganizationScope::class)->withTrashed()->whereNotNull('secret_ciphertext')->orderBy('id')
            ->each(function (PrivilegedAccount $account) use ($vault, &$moved, &$failed): void {
                try {
                    $account->forceFill(['secret_ciphertext' => $vault->encrypt($account->id, $vault->decrypt($account->id, (string) $account->secret_ciphertext))])->saveQuietly();
                    $moved++;
                } catch (Throwable) {
                    $this->error('Could not read the credential of account '.$account->id);
                    $failed++;
                }
            });

        $trail->record(AuditEvent::Updated, null, null, ['pam_rekey' => ['moved' => $moved, 'failed' => $failed]]);
        $this->info("Moved {$moved}, failed {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
