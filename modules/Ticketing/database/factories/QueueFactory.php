<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Domain\Models\Queue;

/**
 * Creates the team in the same organization as the queue.
 *
 * @extends Factory<Queue>
 */
final class QueueFactory extends Factory
{
    protected $model = Queue::class;

    public function definition(): array
    {
        return [
            'organization_id' => app(OrganizationContext::class)->id() ?? Organization::factory(),
            'team_id' => fn (array $attributes): string => Team::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'name' => ucfirst(fake()->unique()->word()).' queue',
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }
}
