<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Application\Exercises;

use Modules\Bcm\Domain\Enums\ExerciseStatus;
use Modules\Bcm\Domain\Models\Exercise;

final readonly class CancelExercise
{
    /**
     * @throws ExerciseClosed once completed or cancelled
     */
    public function handle(Exercise $exercise): void
    {
        if (! $exercise->status->isOpen()) {
            throw new ExerciseClosed;
        }

        // Audited by the exercise's own trail.
        $exercise->forceFill(['status' => ExerciseStatus::Cancelled])->save();
    }
}
