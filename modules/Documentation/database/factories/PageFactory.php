<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Documentation\Domain\Models\Page;
use Modules\Documentation\Domain\Models\Space;

/**
 * @extends Factory<Page>
 */
final class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'organization_id' => fn (array $attributes): string => Space::query()->withoutGlobalScopes()->whereKey($attributes['space_id'])->firstOrFail()->organization_id,
            'title' => fake()->sentence(3),
            'body' => fake()->paragraph(),
        ];
    }
}
