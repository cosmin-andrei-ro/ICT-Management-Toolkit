<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Domain\Models\BusinessCalendar;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * @extends Factory<BusinessCalendar>
 */
final class BusinessCalendarFactory extends Factory
{
    protected $model = BusinessCalendar::class;

    public function definition(): array
    {
        $day = [['09:00', '17:00']];

        return [
            'organization_id' => app(OrganizationContext::class)->id() ?? Organization::factory(),
            'name' => 'Office hours',
            'timezone' => 'Europe/Bucharest',
            'weekly_hours' => [1 => $day, 2 => $day, 3 => $day, 4 => $day, 5 => $day],
            'public_holidays' => 'RO',
            'is_default' => true,
        ];
    }
}
