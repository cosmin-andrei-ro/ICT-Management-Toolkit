<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Bia\Domain\Models\TimeHorizon;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * @extends Factory<TimeHorizon>
 */
final class TimeHorizonFactory extends Factory
{
    protected $model = TimeHorizon::class;

    public function definition(): array
    {
        return [
            'organization_id' => app(OrganizationContext::class)->id() ?? Organization::factory(),
            'label' => fake()->unique()->numerify('# days'),
            'minutes' => fake()->unique()->numberBetween(60, 100000),
        ];
    }
}
