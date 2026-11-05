<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Application\Policies;

use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Pam\Domain\Enums\RequestStatus;
use Modules\Pam\Domain\Models\AccessRequest;
use Modules\Pam\Domain\Models\PrivilegedAccount;

/**
 * The named approver, the owner and those who manage the vault may decide, so that
 * a request is never stuck. Nobody decides on their own.
 */
final readonly class AccessRequestPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function decide(User $user, AccessRequest $request): bool
    {
        return $request->organization_id === $this->context->id()
            && $request->status === RequestStatus::Pending
            && $request->user_id !== $user->id
            && self::mayDecideFor($user, $request->account);
    }

    public static function mayDecideFor(User $user, PrivilegedAccount $account): bool
    {
        return $user->can('pam.account.manage')
            || ($user->can('pam.vault.use') && ($account->approver_id === $user->id || $account->owner_id === $user->id));
    }
}
