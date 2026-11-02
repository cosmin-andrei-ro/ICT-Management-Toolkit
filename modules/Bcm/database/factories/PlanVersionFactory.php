<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bcm\Domain\Models\PlanVersion;

/**
 * @extends Factory<PlanVersion>
 */
final class PlanVersionFactory extends Factory
{
    protected $model = PlanVersion::class;

    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'organization_id' => fn (array $attributes): string => Plan::query()->withoutGlobalScopes()->whereKey($attributes['plan_id'])->firstOrFail()->organization_id,
            'version' => 1,
            'status' => 'draft',
        ];
    }
}
