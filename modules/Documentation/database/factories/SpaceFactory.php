<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Documentation\Domain\Models\Space;

/**
 * @extends Factory<Space>
 */
final class SpaceFactory extends Factory
{
    protected $model = Space::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'visibility' => 'staff',
        ];
    }
}
