<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Risk\Domain\Models\Risk;

/**
 * @extends Factory<Risk>
 */
final class RiskFactory extends Factory
{
    protected $model = Risk::class;

    public function definition(): array
    {
        return [
            'reference' => function (): int {
                $max = DB::table('risk_risks')->where('organization_id', app(OrganizationContext::class)->requireId())->max('reference');

                return (is_numeric($max) ? (int) $max : 0) + 1;
            },
            'title' => fake()->sentence(4),
            'category' => 'information_security',
            'status' => 'open',
        ];
    }
}
