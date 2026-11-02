<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Policies;

use Modules\Bcm\Domain\Enums\PlanType;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Owner's decisions: IT staff read plans (they use them in incidents); the
 * continuity coordinator manages every plan (bcm.plan.manage); team leads
 * manage IT recovery plans (bcm.drp.manage); a plan's owner and deputy keep it
 * up to date.
 */
final readonly class PlanPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $user->can('bcm.plan.view');
    }

    public function view(User $user, Plan $plan): bool
    {
        return $this->inCurrentOrganization($plan) && ($user->can('bcm.plan.view') || $plan->isOwnedBy($user));
    }

    public function create(User $user): bool
    {
        return $user->can('bcm.plan.manage') || $user->can('bcm.drp.manage');
    }

    /**
     * Plan details: name, owners, status.
     */
    public function update(User $user, Plan $plan): bool
    {
        return $this->inCurrentOrganization($plan) && $this->manages($user, $plan->type);
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $this->update($user, $plan);
    }

    /**
     * Start or edit a version of an active plan.
     */
    public function edit(User $user, Plan $plan): bool
    {
        return $this->inCurrentOrganization($plan)
            && $plan->status === 'active'
            && ($plan->isOwnedBy($user) || $this->manages($user, $plan->type));
    }

    /**
     * Plan an exercise: only a plan in force can be tested.
     */
    public function exercise(User $user, Plan $plan): bool
    {
        return $plan->approved_version !== null && $this->edit($user, $plan);
    }

    /**
     * Record a real activation: only a plan in force is activated.
     */
    public function activate(User $user, Plan $plan): bool
    {
        return $this->exercise($user, $plan);
    }

    public function manages(User $user, PlanType $type): bool
    {
        return $user->can('bcm.plan.manage') || ($type === PlanType::Drp && $user->can('bcm.drp.manage'));
    }

    private function inCurrentOrganization(Plan $plan): bool
    {
        return $this->context->has() && $plan->organization_id === $this->context->id();
    }
}
