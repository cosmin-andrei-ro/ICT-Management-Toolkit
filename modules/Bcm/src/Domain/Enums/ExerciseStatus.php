<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Domain\Enums;

/**
 * Planned, then in progress, then completed; cancelled any time before completion. Mirrored by bcm_exercises_status_check.
 */
enum ExerciseStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Results and findings can still be recorded.
     */
    public function isOpen(): bool
    {
        return $this === self::Planned || $this === self::InProgress;
    }

    public function label(): string
    {
        return __('bcm::exercises.statuses.'.$this->value);
    }
}
