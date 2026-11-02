<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Policies;

use Modules\Bcm\Domain\Models\Activation;
use Modules\Core\Domain\Models\User;

/**
 * Whoever may see a plan sees its activations; whoever keeps it up to date
 * records them, until they are closed.
 */
final readonly class ActivationPolicy
{
    public function __construct(private PlanPolicy $plans) {}

    public function view(User $user, Activation $activation): bool
    {
        return $this->plans->view($user, $activation->plan);
    }

    public function update(User $user, Activation $activation): bool
    {
        return $activation->isOpen() && $this->plans->edit($user, $activation->plan);
    }
}
