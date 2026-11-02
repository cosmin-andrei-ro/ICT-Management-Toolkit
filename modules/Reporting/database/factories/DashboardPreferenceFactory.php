<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Domain\Models\User;
use Modules\Reporting\Domain\Models\DashboardPreference;

/**
 * @extends Factory<DashboardPreference>
 */
final class DashboardPreferenceFactory extends Factory
{
    protected $model = DashboardPreference::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'hidden_metrics' => [],
            'source_order' => [],
        ];
    }
}
