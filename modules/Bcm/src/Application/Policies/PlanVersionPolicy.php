<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Policies;

use Illuminate\Contracts\Auth\Access\Gate;
use Modules\Bcm\Domain\Enums\VersionStatus;
use Modules\Bcm\Domain\Models\PlanVersion;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Versions follow their plan; only open ones change. Approval is a separate
 * duty: whoever owns the plan or submitted the version cannot approve it.
 */
final readonly class PlanVersionPolicy
{
    public function __construct(
        private OrganizationContext $context,
        private Gate $gate,
    ) {}

    public function view(User $user, PlanVersion $version): bool
    {
        return $this->inCurrentOrganization($version) && $this->gate->forUser($user)->allows('view', $version->plan);
    }

    public function update(User $user, PlanVersion $version): bool
    {
        return $this->inCurrentOrganization($version)
            && $version->status->isEditable()
            && $this->gate->forUser($user)->allows('edit', $version->plan);
    }

    public function decide(User $user, PlanVersion $version): bool
    {
        return $this->inCurrentOrganization($version)
            && $version->status === VersionStatus::Submitted
            && $user->can('bcm.plan.approve')
            && ! $version->plan->isOwnedBy($user)
            && $version->submitted_by !== $user->id;
    }

    private function inCurrentOrganization(PlanVersion $version): bool
    {
        return $this->context->has() && $version->organization_id === $this->context->id();
    }
}
