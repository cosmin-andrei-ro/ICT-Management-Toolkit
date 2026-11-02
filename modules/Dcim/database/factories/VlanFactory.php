<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Dcim\Domain\Models\Vlan;

/**
 * @extends Factory<Vlan>
 */
final class VlanFactory extends Factory
{
    protected $model = Vlan::class;

    public function definition(): array
    {
        return ['vid' => fake()->unique()->numberBetween(2, 4094), 'name' => fake()->unique()->word()];
    }
}
