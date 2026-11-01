<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Application\Policies;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Item types and their attributes: configuration, for cmdb.type.manage
 * (team leads and administrators).
 */
final readonly class CiTypePolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('cmdb.type.manage');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->inCurrentOrganization($model) && $user->can('cmdb.type.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('cmdb.type.manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->inCurrentOrganization($model) && $user->can('cmdb.type.manage');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->inCurrentOrganization($model) && $user->can('cmdb.type.manage');
    }

    private function inCurrentOrganization(Model $model): bool
    {
        return $this->context->has() && $model->getAttribute('organization_id') === $this->context->id();
    }
}
