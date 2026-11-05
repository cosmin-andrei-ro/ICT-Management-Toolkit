<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Application\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Domain\Models\User;
use Modules\Pam\Application\Notifications\AccessGranted;
use Modules\Pam\Domain\Models\AccountGrant;
use Modules\Pam\Domain\Models\PrivilegedAccount;

/**
 * Gives one person access to a credential, and tells those who should know. The
 * caller checked the policy and the second factor.
 */
final class GrantAccess
{
    public function handle(PrivilegedAccount $account, User $person, User $actor): void
    {
        AccountGrant::query()->firstOrCreate(['account_id' => $account->id, 'user_id' => $person->id]);

        $managers = User::query()->where('is_active', true)
            ->whereHas('organizations', fn (Builder $query) => $query->whereKey($account->organization_id))
            ->get()
            ->filter(fn (User $user): bool => $user->can('pam.account.manage'));

        $recipients = $managers->push($person);

        if ($account->owner !== null) {
            $recipients->push($account->owner);
        }

        $recipients = $recipients->unique('id')->reject(fn (User $user): bool => $user->id === $actor->id);

        Notification::send($recipients, new AccessGranted($account->name, $person->name, $actor->name, route('pam.accounts.show', $account)));
    }
}
