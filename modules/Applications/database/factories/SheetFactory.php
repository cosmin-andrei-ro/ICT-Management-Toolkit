<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Applications\Domain\Models\Application;
use Modules\Applications\Domain\Models\Sheet;

/**
 * @extends Factory<Sheet>
 */
final class SheetFactory extends Factory
{
    protected $model = Sheet::class;

    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'organization_id' => fn (array $attributes): string => Application::query()->withoutGlobalScopes()->whereKey($attributes['application_id'])->firstOrFail()->organization_id,
            'status' => 'draft',
            'is_major' => true,
        ];
    }
}
