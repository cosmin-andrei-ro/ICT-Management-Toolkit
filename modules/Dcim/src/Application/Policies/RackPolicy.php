<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Application\Policies;

use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Dcim\Domain\Models\Rack;

/**
 * IT staff see the racks; the people who run the rooms change them and what
 * is mounted in them.
 */
final readonly class RackPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('dcim.infrastructure.view');
    }

    public function view(User $user, Rack $rack): bool
    {
        return $this->inCurrentOrganization($rack) && $user->can('dcim.infrastructure.view');
    }

    public function create(User $user): bool
    {
        return $user->can('dcim.infrastructure.manage');
    }

    public function update(User $user, Rack $rack): bool
    {
        return $this->inCurrentOrganization($rack) && $user->can('dcim.infrastructure.manage');
    }

    public function delete(User $user, Rack $rack): bool
    {
        return $this->update($user, $rack);
    }

    private function inCurrentOrganization(Rack $rack): bool
    {
        return $this->context->has() && $rack->organization_id === $this->context->id();
    }
}
