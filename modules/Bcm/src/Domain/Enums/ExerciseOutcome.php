<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Domain\Enums;

/**
 * Mirrored by bcm_exercises_outcome_check.
 */
enum ExerciseOutcome: string
{
    case Successful = 'successful';
    case Partial = 'partial';
    case Failed = 'failed';

    public function label(): string
    {
        return __('bcm::exercises.outcomes.'.$this->value);
    }
}
