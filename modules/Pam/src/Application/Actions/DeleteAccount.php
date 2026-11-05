<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Modules\Pam\Domain\Models\PrivilegedAccount;

/**
 * The record stays for the audit trail; the credential does not.
 */
final readonly class DeleteAccount
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(PrivilegedAccount $account): void
    {
        $this->db->transaction(function () use ($account): void {
            $account->forceFill(['secret_ciphertext' => null])->saveQuietly();
            $account->grants()->get()->each->delete();
            $account->delete();
        });
    }
}
