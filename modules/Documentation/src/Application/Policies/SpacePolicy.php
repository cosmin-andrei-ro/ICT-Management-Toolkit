<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Application\Policies;

use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Documentation\Application\SpaceAccess;
use Modules\Documentation\Domain\Models\Space;

final readonly class SpacePolicy
{
    public function __construct(
        private OrganizationContext $context,
        private SpaceAccess $access,
    ) {}

    /**
     * Everyone may look for documentation; the list shows only what they read.
     */
    public function viewAny(User $user): bool
    {
        return $this->context->has();
    }

    public function view(User $user, Space $space): bool
    {
        return $this->inCurrentOrganization($space) && $this->access->canRead($user, $space);
    }

    public function create(User $user): bool
    {
        return $user->can('documentation.space.manage');
    }

    /**
     * Name, visibility, owners and teams: documentation managers and the space's owners.
     */
    public function update(User $user, Space $space): bool
    {
        return $this->inCurrentOrganization($space) && ($user->can('documentation.space.manage') || $space->owners()->whereKey($user->id)->exists());
    }

    public function delete(User $user, Space $space): bool
    {
        return $this->inCurrentOrganization($space) && $user->can('documentation.space.manage');
    }

    /**
     * Writing pages.
     */
    public function edit(User $user, Space $space): bool
    {
        return $this->inCurrentOrganization($space) && $this->access->canEdit($user, $space);
    }

    private function inCurrentOrganization(Space $space): bool
    {
        return $this->context->has() && $space->organization_id === $this->context->id();
    }
}
