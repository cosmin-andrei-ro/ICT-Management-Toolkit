<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Application\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Tenancy\OrganizationScope;
use Modules\Pam\Application\Notifications\AccessUpdate;
use Modules\Pam\Domain\Models\PrivilegedAccount;

/**
 * Reminds an account's owner, or else those who manage the vault, of a credential
 * to rotate or an access list to review. Once per duty, until it is done.
 */
final readonly class RemindVaultDuties
{
    private const int DAYS_BEFORE = 14;

    public function __construct(private OrganizationContext $context) {}

    public function handle(): int
    {
        $sent = 0;

        PrivilegedAccount::query()->withoutGlobalScope(OrganizationScope::class)
            ->where(fn (Builder $query) => $query->whereNotNull('rotation_days')->orWhereNotNull('review_days'))
            ->orderBy('id')
            ->each(function (PrivilegedAccount $account) use (&$sent): void {
                $this->context->runAs($account->organization_id, function () use ($account, &$sent): void {
                    $limit = now()->addDays(self::DAYS_BEFORE);

                    if ($account->rotation_reminded_at === null && $account->rotationDueOn()?->lte($limit) === true) {
                        $this->remind($account, 'rotation_due');
                        $account->forceFill(['rotation_reminded_at' => now()])->saveQuietly();
                        $sent++;
                    }

                    if ($account->review_reminded_at === null && $account->reviewDueOn()?->lte($limit) === true) {
                        $this->remind($account, 'review_due');
                        $account->forceFill(['review_reminded_at' => now()])->saveQuietly();
                        $sent++;
                    }
                });
            });

        return $sent;
    }

    private function remind(PrivilegedAccount $account, string $kind): void
    {
        Notification::send($this->recipients($account), new AccessUpdate($kind, $account->name, route('pam.accounts.show', $account)));
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(PrivilegedAccount $account): Collection
    {
        $owner = $account->owner;

        if ($owner !== null && $owner->canSignIn()) {
            return new Collection([$owner]);
        }

        return User::query()->where('is_active', true)
            ->whereHas('organizations', fn (Builder $query) => $query->whereKey($account->organization_id))
            ->get()->toBase()->filter(fn (User $user): bool => $user->can('pam.account.manage'))->values();
    }
}
