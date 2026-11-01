<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Policies;

use Modules\Core\Domain\Models\Department;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

final readonly class DepartmentPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('core.department.view');
    }

    public function view(User $user, Department $department): bool
    {
        return $this->inCurrentOrganization($department) && $user->can('core.department.view');
    }

    public function create(User $user): bool
    {
        return $user->can('core.department.create');
    }

    public function update(User $user, Department $department): bool
    {
        return $this->inCurrentOrganization($department) && $user->can('core.department.update');
    }

    public function delete(User $user, Department $department): bool
    {
        return $this->inCurrentOrganization($department) && $user->can('core.department.delete');
    }

    /**
     * The global scope already hides other organizations' rows; this guards
     * against a model loaded without it (e.g. withoutGlobalScopes in a job).
     */
    private function inCurrentOrganization(Department $department): bool
    {
        return $this->context->has() && $department->organization_id === $this->context->id();
    }
}
