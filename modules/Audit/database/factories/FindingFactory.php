<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Domain\Models\Finding;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * @extends Factory<Finding>
 */
final class FindingFactory extends Factory
{
    protected $model = Finding::class;

    public function definition(): array
    {
        return [
            'number' => function (): int {
                $max = DB::table('audit_findings')->where('organization_id', app(OrganizationContext::class)->requireId())->max('number');

                return (is_numeric($max) ? (int) $max : 0) + 1;
            },
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'severity' => 'minor_nonconformity',
        ];
    }
}
