<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Application\Policies;

use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Pam\Domain\Models\PrivilegedAccount;

/**
 * Deny by default, and managing is not reading: those who run the vault see no
 * credential unless someone else granted it to them. Nobody grants themselves.
 */
final readonly class PrivilegedAccountPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('pam.vault.use') || $user->can('pam.account.manage');
    }

    public function view(User $user, PrivilegedAccount $account): bool
    {
        return $this->inCurrentOrganization($account) && ($this->update($user, $account) || $this->isGranted($user, $account));
    }

    public function create(User $user): bool
    {
        return $user->can('pam.account.manage');
    }

    public function update(User $user, PrivilegedAccount $account): bool
    {
        return $this->inCurrentOrganization($account) && ($user->can('pam.account.manage') || ($account->owner_id === $user->id && $user->can('pam.vault.use')));
    }

    public function delete(User $user, PrivilegedAccount $account): bool
    {
        return $this->inCurrentOrganization($account) && $user->can('pam.account.manage');
    }

    /**
     * Ask for the credential. Showing it also needs a fresh second factor (RevealSecret).
     */
    public function reveal(User $user, PrivilegedAccount $account): bool
    {
        return $this->inCurrentOrganization($account) && $user->can('pam.vault.use') && $this->isGranted($user, $account);
    }

    public function grantTo(User $user, PrivilegedAccount $account, User $person): bool
    {
        return $this->update($user, $account) && $user->id !== $person->id;
    }

    private function isGranted(User $user, PrivilegedAccount $account): bool
    {
        return $account->grants()->where('user_id', $user->id)->exists();
    }

    private function inCurrentOrganization(PrivilegedAccount $account): bool
    {
        return $account->organization_id === $this->context->id();
    }
}
