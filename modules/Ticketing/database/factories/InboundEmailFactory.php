<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Ticketing\Domain\Email\InboundOutcome;
use Modules\Ticketing\Domain\Models\InboundEmail;
use Modules\Ticketing\Domain\Models\Mailbox;

/**
 * Creates the mailbox in the same organization.
 *
 * @extends Factory<InboundEmail>
 */
final class InboundEmailFactory extends Factory
{
    protected $model = InboundEmail::class;

    public function definition(): array
    {
        return [
            'mailbox_id' => Mailbox::factory(),
            'organization_id' => fn (array $attributes): string => Mailbox::query()->withoutGlobalScopes()->whereKey($attributes['mailbox_id'])->firstOrFail()->organization_id,
            'message_key' => hash('sha256', fake()->unique()->uuid()),
            'from_address' => fake()->safeEmail(),
            'subject' => fake()->sentence(),
            'outcome' => InboundOutcome::Created,
            'processed_at' => now(),
        ];
    }
}
