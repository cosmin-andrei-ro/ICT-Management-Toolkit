<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\SupplierContact;

/**
 * @extends Factory<SupplierContact>
 */
final class SupplierContactFactory extends Factory
{
    protected $model = SupplierContact::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'organization_id' => fn (array $attributes): string => Supplier::query()->withoutGlobalScopes()->whereKey($attributes['supplier_id'])->firstOrFail()->organization_id,
            'name' => fake()->name(),
            'role' => fake()->jobTitle(),
            'email' => fake()->safeEmail(),
        ];
    }
}
