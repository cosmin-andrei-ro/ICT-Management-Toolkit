<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Itam\Domain\Models\License;
use Modules\Itam\Domain\Models\SoftwareProduct;

/**
 * @extends Factory<License>
 */
final class LicenseFactory extends Factory
{
    protected $model = License::class;

    public function definition(): array
    {
        return [
            'product_id' => SoftwareProduct::factory(),
            'organization_id' => fn (array $attributes): string => SoftwareProduct::query()->withoutGlobalScopes()->whereKey($attributes['product_id'])->firstOrFail()->organization_id,
            'name' => fake()->unique()->bothify('Agreement ####'),
            'model' => 'per_user',
            'quantity' => 10,
        ];
    }
}
