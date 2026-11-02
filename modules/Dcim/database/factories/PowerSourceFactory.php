<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Domain\Models\Location;
use Modules\Dcim\Domain\Models\PowerSource;

/**
 * @extends Factory<PowerSource>
 */
final class PowerSourceFactory extends Factory
{
    protected $model = PowerSource::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory()->state(['kind' => 'room']),
            'kind' => 'pdu',
            'name' => 'PDU-'.fake()->unique()->numberBetween(1, 99999),
            'capacity_watts' => 3600,
        ];
    }
}
