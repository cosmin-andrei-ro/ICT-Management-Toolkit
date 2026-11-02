<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * @extends Factory<Plan>
 */
final class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'organization_id' => app(OrganizationContext::class)->id() ?? Organization::factory(),
            'type' => 'drp',
            'name' => fake()->unique()->words(3, true),
        ];
    }
}
