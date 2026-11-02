<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Policies;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Dropdown values: configuration, for applications.settings.manage. Values
 * are deactivated rather than deleted, so sheets keep their labels.
 */
final readonly class SettingsPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('applications.settings.manage');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->inCurrentOrganization($model) && $user->can('applications.settings.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('applications.settings.manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->inCurrentOrganization($model) && $user->can('applications.settings.manage');
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    private function inCurrentOrganization(Model $model): bool
    {
        return $this->context->has() && $model->getAttribute('organization_id') === $this->context->id();
    }
}
