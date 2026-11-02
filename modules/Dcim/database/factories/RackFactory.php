<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Domain\Models\Location;
use Modules\Dcim\Domain\Models\Rack;

/**
 * @extends Factory<Rack>
 */
final class RackFactory extends Factory
{
    protected $model = Rack::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory()->state(['kind' => 'room']),
            'name' => 'R'.fake()->unique()->numberBetween(1, 9999),
            'height_u' => 42,
        ];
    }
}
