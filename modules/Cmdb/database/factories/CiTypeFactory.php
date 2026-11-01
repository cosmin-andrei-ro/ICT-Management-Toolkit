<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * @extends Factory<CiType>
 */
final class CiTypeFactory extends Factory
{
    protected $model = CiType::class;

    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word()).' type';

        return [
            'organization_id' => app(OrganizationContext::class)->id() ?? Organization::factory(),
            'key' => strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $name)),
            'name' => $name,
            'attribute_schema' => [],
            'is_active' => true,
        ];
    }
}
