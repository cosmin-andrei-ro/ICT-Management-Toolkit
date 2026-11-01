<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Policies;

use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

final readonly class TeamPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('core.team.view');
    }

    public function view(User $user, Team $team): bool
    {
        return $this->inCurrentOrganization($team) && $user->can('core.team.view');
    }

    public function create(User $user): bool
    {
        return $user->can('core.team.manage');
    }

    public function update(User $user, Team $team): bool
    {
        return $this->inCurrentOrganization($team) && $user->can('core.team.manage');
    }

    public function delete(User $user, Team $team): bool
    {
        return $this->inCurrentOrganization($team) && $user->can('core.team.manage');
    }

    private function inCurrentOrganization(Team $team): bool
    {
        return $this->context->has() && $team->organization_id === $this->context->id();
    }
}
