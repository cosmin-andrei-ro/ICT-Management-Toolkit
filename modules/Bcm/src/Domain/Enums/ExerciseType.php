<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Domain\Enums;

/**
 * How deep an exercise goes, from a discussion around a table to a real interruption. Mirrored by bcm_exercises_type_check.
 */
enum ExerciseType: string
{
    case Tabletop = 'tabletop';
    case Walkthrough = 'walkthrough';
    case Technical = 'technical';
    case Full = 'full';

    public function label(): string
    {
        return __('bcm::exercises.types.'.$this->value);
    }
}
