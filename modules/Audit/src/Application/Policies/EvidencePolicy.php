<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Policies;

use Illuminate\Contracts\Auth\Access\Gate;
use Modules\Audit\Domain\Models\Evidence;
use Modules\Core\Domain\Models\User;

/**
 * Evidence is seen by whoever sees its control (its documents too, through
 * Core's attachment download); never internal.
 */
final readonly class EvidencePolicy
{
    public function __construct(private Gate $gate) {}

    public function view(User $user, Evidence $evidence): bool
    {
        return $this->gate->forUser($user)->allows('view', $evidence->control);
    }

    public function viewInternal(User $user, Evidence $evidence): bool
    {
        return false;
    }

    /**
     * Evidence has no comment thread.
     */
    public function comment(User $user, Evidence $evidence): bool
    {
        return false;
    }

    public function commentInternally(User $user, Evidence $evidence): bool
    {
        return false;
    }
}
