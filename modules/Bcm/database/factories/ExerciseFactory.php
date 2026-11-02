<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Bcm\Domain\Models\Exercise;
use Modules\Bcm\Domain\Models\Plan;

/**
 * @extends Factory<Exercise>
 */
final class ExerciseFactory extends Factory
{
    protected $model = Exercise::class;

    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'organization_id' => fn (array $attributes): string => Plan::query()->withoutGlobalScopes()->whereKey($attributes['plan_id'])->firstOrFail()->organization_id,
            'type' => 'tabletop',
            'title' => fake()->sentence(3),
            'scheduled_on' => now()->addMonth()->toDateString(),
            'status' => 'planned',
        ];
    }
}
