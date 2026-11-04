<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\Category;

/**
 * @extends Factory<Asset>
 */
final class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'organization_id' => fn (array $attributes): string => Category::query()->withoutGlobalScopes()->whereKey($attributes['category_id'])->firstOrFail()->organization_id,
            'asset_tag' => 'T-'.fake()->unique()->numerify('######'),
            'manufacturer' => fake()->randomElement(['Alpha', 'Beta', 'Gamma']),
            'model' => fake()->bothify('Model ##??'),
            'serial_number' => fake()->unique()->bothify('SN########'),
            'status' => 'in_stock',
        ];
    }
}
