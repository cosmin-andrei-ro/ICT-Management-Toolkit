<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Domain\Enums;

/**
 * What happened to a runbook step during an exercise. Mirrored by bcm_exercise_steps_result_check.
 */
enum StepResult: string
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Issues = 'issues';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return __('bcm::exercises.results.'.$this->value);
    }
}
