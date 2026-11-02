<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Applications\Domain\Models\Application;

/**
 * @extends Factory<Application>
 */
final class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    public function definition(): array
    {
        return [
            'code' => 'APP-'.strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->unique()->words(2, true),
        ];
    }
}
