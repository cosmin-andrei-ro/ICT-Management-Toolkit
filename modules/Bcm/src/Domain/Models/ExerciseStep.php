<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Bcm\Domain\Enums\Phase;
use Modules\Bcm\Domain\Enums\StepResult;
use Modules\Core\Domain\Concerns\BelongsToOrganization;

/**
 * A runbook step as exercised: copied from the plan version when the exercise
 * starts, with what happened. Written by the exercise actions, which audit the
 * results on the exercise.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $exercise_id
 * @property int $position
 * @property Phase $phase
 * @property string $action
 * @property ?int $estimated_minutes
 * @property ?string $configuration_item_id
 * @property StepResult $result
 * @property ?int $actual_minutes
 * @property ?string $notes
 */
#[Table('bcm_exercise_steps')]
final class ExerciseStep extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    public $timestamps = false;

    /** @var array<string, mixed> */
    protected $attributes = [
        'result' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'phase' => Phase::class,
            'result' => StepResult::class,
            'position' => 'integer',
            'estimated_minutes' => 'integer',
            'actual_minutes' => 'integer',
        ];
    }
}
