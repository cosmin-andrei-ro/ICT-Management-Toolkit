<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Dcim\Domain\Models\Cable;

/**
 * @extends Factory<Cable>
 */
final class CableFactory extends Factory
{
    protected $model = Cable::class;

    public function definition(): array
    {
        return [
            'label' => 'C-'.fake()->unique()->numerify('#####'),
            'type' => 'cat6a',
        ];
    }
}
