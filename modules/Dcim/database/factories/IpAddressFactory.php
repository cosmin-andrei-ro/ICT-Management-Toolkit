<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Dcim\Domain\Models\IpAddress;

/**
 * @extends Factory<IpAddress>
 */
final class IpAddressFactory extends Factory
{
    protected $model = IpAddress::class;

    public function definition(): array
    {
        return ['address' => '192.168.'.fake()->numberBetween(0, 255).'.'.fake()->unique()->numberBetween(1, 254)];
    }
}
