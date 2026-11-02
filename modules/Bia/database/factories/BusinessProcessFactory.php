<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * @extends Factory<BusinessProcess>
 */
final class BusinessProcessFactory extends Factory
{
    protected $model = BusinessProcess::class;

    public function definition(): array
    {
        return [
            'organization_id' => app(OrganizationContext::class)->id() ?? Organization::factory(),
            'name' => fake()->unique()->words(3, true),
        ];
    }
}
