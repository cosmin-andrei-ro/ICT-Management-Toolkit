<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Application\Listeners;

use Modules\Core\Domain\Events\PersonLeftOrganization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Pam\Application\Actions\ManageAccessRequests;
use Modules\Pam\Domain\Models\AccountGrant;
use Modules\Pam\Domain\Models\PrivilegedAccount;

/**
 * Someone who left has no access to come back to if they are added again later.
 */
final readonly class DropAccessOfLeaver
{
    public function __construct(private OrganizationContext $context, private ManageAccessRequests $requests) {}

    public function handle(PersonLeftOrganization $event): void
    {
        $this->context->runAs($event->organizationId, function () use ($event): void {
            AccountGrant::query()->where('user_id', $event->user->id)->get()->each->delete();
            $this->requests->endFor($event->user->id);
            PrivilegedAccount::query()->where('owner_id', $event->user->id)->get()->each(fn (PrivilegedAccount $account) => $account->update(['owner_id' => null]));
            PrivilegedAccount::query()->where('approver_id', $event->user->id)->get()->each(fn (PrivilegedAccount $account) => $account->update(['approver_id' => null]));
        });
    }
}
