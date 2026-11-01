<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Cmdb\Domain\Enums\CiStatus;
use Modules\Cmdb\Domain\Enums\Criticality;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Creates the type in the same organization as the item.
 *
 * @extends Factory<ConfigurationItem>
 */
final class ConfigurationItemFactory extends Factory
{
    protected $model = ConfigurationItem::class;

    public function definition(): array
    {
        return [
            'organization_id' => app(OrganizationContext::class)->id() ?? Organization::factory(),
            'type_id' => fn (array $attributes): string => CiType::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'name' => fake()->unique()->domainWord().'-'.fake()->numberBetween(1, 99),
            'status' => CiStatus::InUse,
            'criticality' => Criticality::Medium,
            'properties' => (object) [],
        ];
    }

    public function critical(): static
    {
        return $this->state(fn (): array => ['criticality' => Criticality::Critical]);
    }
}
