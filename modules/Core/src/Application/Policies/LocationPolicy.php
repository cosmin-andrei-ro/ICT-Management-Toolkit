<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Policies;

use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Places are configuration, managed in the panel. Forms of other records list
 * them through Location::paths(), without this policy.
 */
final readonly class LocationPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('core.location.manage');
    }

    public function view(User $user, Location $location): bool
    {
        return $this->inCurrentOrganization($location) && $user->can('core.location.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('core.location.manage');
    }

    public function update(User $user, Location $location): bool
    {
        return $this->inCurrentOrganization($location) && $user->can('core.location.manage');
    }

    public function delete(User $user, Location $location): bool
    {
        return $this->inCurrentOrganization($location) && $user->can('core.location.manage');
    }

    private function inCurrentOrganization(Location $location): bool
    {
        return $this->context->has() && $location->organization_id === $this->context->id();
    }
}
