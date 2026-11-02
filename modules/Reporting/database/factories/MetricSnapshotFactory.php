<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reporting\Domain\Models\MetricSnapshot;

/**
 * @extends Factory<MetricSnapshot>
 */
final class MetricSnapshotFactory extends Factory
{
    protected $model = MetricSnapshot::class;

    public function definition(): array
    {
        return [
            'metric_key' => 'ticketing.open',
            'taken_on' => today(),
            'value' => fake()->numberBetween(0, 40),
        ];
    }
}
