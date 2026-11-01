<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Models\Service;

/**
 * @extends Factory<Service>
 */
final class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'organization_id' => app(OrganizationContext::class)->id() ?? Organization::factory(),
            'name' => ucfirst(fake()->unique()->word()).' service',
            'impact' => Impact::Low,
            'urgency' => Urgency::Low,
            'form_schema' => [],
            'is_active' => true,
        ];
    }
}
