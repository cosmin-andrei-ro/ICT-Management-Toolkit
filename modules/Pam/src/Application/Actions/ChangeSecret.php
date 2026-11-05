<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Application\Actions;

use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;
use Modules\Pam\Application\PamVault;
use Modules\Pam\Domain\Exceptions\VaultNotConfigured;
use Modules\Pam\Domain\Models\PrivilegedAccount;
use SensitiveParameter;

/**
 * Stores a new credential. It only records it here: changing it on the target
 * system stays a person's job (ADR 0078). The trail says it changed, not to what.
 */
final readonly class ChangeSecret
{
    public function __construct(
        private PamVault $vault,
        private AuditTrail $trail,
    ) {}

    /**
     * @throws VaultNotConfigured
     */
    public function handle(PrivilegedAccount $account, #[SensitiveParameter] string $secret, User $actor): void
    {
        $account->forceFill(['secret_ciphertext' => $this->vault->encrypt($account->id, $secret), 'secret_changed_at' => now()])->saveQuietly();
        $this->trail->record(AuditEvent::Updated, $account, null, ['secret' => 'changed'], $actor->id);
    }
}
