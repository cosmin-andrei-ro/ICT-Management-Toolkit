<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\Port;

/**
 * @extends Factory<Port>
 */
final class PortFactory extends Factory
{
    protected $model = Port::class;

    public function definition(): array
    {
        return [
            'mount_id' => Mount::factory(),
            'name' => 'eth'.fake()->unique()->numberBetween(0, 99999),
            'kind' => 'rj45',
            'side' => 'front',
        ];
    }
}
