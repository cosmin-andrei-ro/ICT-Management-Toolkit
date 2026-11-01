<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Policies;

use Modules\Core\Domain\Directory\DirectoryGroupMapping;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Mappings grant roles, so managing them is as sensitive as managing roles.
 */
final readonly class DirectoryGroupMappingPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('core.directory.manage');
    }

    public function view(User $user, DirectoryGroupMapping $mapping): bool
    {
        return $this->inCurrentOrganization($mapping) && $user->can('core.directory.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('core.directory.manage');
    }

    public function update(User $user, DirectoryGroupMapping $mapping): bool
    {
        return $this->inCurrentOrganization($mapping) && $user->can('core.directory.manage');
    }

    public function delete(User $user, DirectoryGroupMapping $mapping): bool
    {
        return $this->inCurrentOrganization($mapping) && $user->can('core.directory.manage');
    }

    private function inCurrentOrganization(DirectoryGroupMapping $mapping): bool
    {
        return $this->context->has() && $mapping->organization_id === $this->context->id();
    }
}
