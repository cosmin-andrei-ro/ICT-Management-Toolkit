<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Policies;

use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * People management in the current organization (administrators by default).
 * Nobody changes their own roles, status or membership here, so an
 * administrator cannot lock themselves out by mistake.
 */
final readonly class UserPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('core.user.update');
    }

    public function view(User $user, User $model): bool
    {
        return $this->inCurrentOrganization($model) && $user->can('core.user.update');
    }

    public function create(User $user): bool
    {
        return $user->can('core.user.create');
    }

    public function update(User $user, User $model): bool
    {
        return $this->inCurrentOrganization($model) && $user->can('core.user.update');
    }

    public function manageAccess(User $user, User $model): bool
    {
        return $user->id !== $model->id && $this->update($user, $model);
    }

    /**
     * Changes to the account itself (name, second factor, lock), which every
     * organization it belongs to relies on: only when it belongs to this one alone.
     */
    public function manageAccount(User $user, User $model): bool
    {
        return $this->manageAccess($user, $model) && $model->organizations()->count() === 1;
    }

    public function deactivate(User $user, User $model): bool
    {
        // The account is shared by every organization it belongs to.
        return $user->id !== $model->id
            && $this->inCurrentOrganization($model)
            && $model->organizations()->count() === 1
            && $user->can('core.user.deactivate');
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    private function inCurrentOrganization(User $model): bool
    {
        $organizationId = $this->context->id();

        return $organizationId !== null && $model->isMemberOf($organizationId);
    }
}
