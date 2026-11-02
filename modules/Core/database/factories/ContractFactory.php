<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Supplier;

/**
 * @extends Factory<Contract>
 */
final class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'organization_id' => fn (array $attributes): string => Supplier::query()->withoutGlobalScopes()->whereKey($attributes['supplier_id'])->firstOrFail()->organization_id,
            'title' => fake()->unique()->sentence(3),
            'type' => 'support',
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ];
    }
}
