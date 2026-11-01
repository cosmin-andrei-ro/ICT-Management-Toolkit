<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Domain\Exports\ExportFormat;
use Modules\Core\Domain\Exports\ExportStatus;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * @extends Factory<Export>
 */
final class ExportFactory extends Factory
{
    protected $model = Export::class;

    public function definition(): array
    {
        return [
            'organization_id' => app(OrganizationContext::class)->id() ?? Organization::factory(),
            'user_id' => User::factory(),
            'type' => 'test.export',
            'format' => ExportFormat::Csv,
            'parameters' => ['from' => '2026-10-01'],
            'status' => ExportStatus::Pending,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (): array => [
            'status' => ExportStatus::Ready,
            'disk' => 'exports',
            'path' => 'test/'.fake()->uuid(),
            'filename' => 'export.csv',
            'row_count' => 1,
            'completed_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);
    }
}
