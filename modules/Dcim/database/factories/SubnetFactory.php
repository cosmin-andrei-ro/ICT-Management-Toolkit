<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Dcim\Domain\Models\Subnet;

/**
 * @extends Factory<Subnet>
 */
final class SubnetFactory extends Factory
{
    protected $model = Subnet::class;

    public function definition(): array
    {
        return ['network' => '10.'.fake()->unique()->numberBetween(0, 255).'.0.0/24'];
    }
}
