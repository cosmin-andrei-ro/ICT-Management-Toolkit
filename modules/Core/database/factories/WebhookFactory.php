<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Webhook;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * @extends Factory<Webhook>
 */
final class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    public function definition(): array
    {
        return [
            'organization_id' => app(OrganizationContext::class)->id() ?? Organization::factory(),
            'name' => fake()->words(2, true),
            'url' => 'https://hooks.example.com/'.fake()->slug(2),
            'secret' => 'whsec_'.fake()->sha256(),
            'events' => ['ticket.created'],
            'is_active' => true,
        ];
    }
}
