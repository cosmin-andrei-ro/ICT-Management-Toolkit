<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Itam\Domain\Models\SoftwareProduct;

/**
 * @extends Factory<SoftwareProduct>
 */
final class SoftwareProductFactory extends Factory
{
    protected $model = SoftwareProduct::class;

    public function definition(): array
    {
        return [
            'organization_id' => app(OrganizationContext::class)->id() ?? Organization::factory(),
            'name' => fake()->unique()->words(2, true),
            'publisher' => fake()->company(),
        ];
    }
}
