<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Policies;

use Modules\Bcm\Domain\Models\Exercise;
use Modules\Core\Domain\Models\User;

/**
 * Whoever may see a plan sees its exercises; whoever keeps the plan up to date
 * (owner, deputy, manager) runs them and records their results.
 */
final readonly class ExercisePolicy
{
    public function __construct(private PlanPolicy $plans) {}

    public function view(User $user, Exercise $exercise): bool
    {
        return $this->plans->view($user, $exercise->plan);
    }

    /**
     * Start, record results and findings, complete or cancel.
     */
    public function update(User $user, Exercise $exercise): bool
    {
        return $exercise->status->isOpen() && $this->plans->edit($user, $exercise->plan);
    }

    /**
     * Raise a problem from a finding, also after completion: the fix comes later.
     */
    public function raiseTicket(User $user, Exercise $exercise): bool
    {
        return $exercise->status->value !== 'cancelled' && $this->plans->edit($user, $exercise->plan);
    }
}
