<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Bia\Domain\Models\BusinessProcess;

/**
 * @extends Factory<Assessment>
 */
final class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    public function definition(): array
    {
        return [
            'process_id' => BusinessProcess::factory(),
            'organization_id' => fn (array $attributes): string => BusinessProcess::query()->withoutGlobalScopes()->whereKey($attributes['process_id'])->firstOrFail()->organization_id,
            'version' => 1,
            'status' => 'draft',
        ];
    }
}
