<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Policies;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Mailboxes hold credentials: only ticketing.mailbox.manage (administrators by
 * default) sees or changes them, and their message log.
 */
final readonly class MailboxPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('ticketing.mailbox.manage');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->inCurrentOrganization($model) && $user->can('ticketing.mailbox.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('ticketing.mailbox.manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->inCurrentOrganization($model) && $user->can('ticketing.mailbox.manage');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->inCurrentOrganization($model) && $user->can('ticketing.mailbox.manage');
    }

    private function inCurrentOrganization(Model $model): bool
    {
        return $this->context->has() && $model->getAttribute('organization_id') === $this->context->id();
    }
}
