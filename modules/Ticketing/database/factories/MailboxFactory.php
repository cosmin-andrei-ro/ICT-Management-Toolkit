<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Domain\Email\MailEncryption;
use Modules\Ticketing\Domain\Models\Mailbox;

/**
 * @extends Factory<Mailbox>
 */
final class MailboxFactory extends Factory
{
    protected $model = Mailbox::class;

    public function definition(): array
    {
        return [
            'organization_id' => app(OrganizationContext::class)->id() ?? Organization::factory(),
            'name' => 'IT support',
            'address' => fake()->unique()->userName().'@example.com',
            'host' => 'mail.example.com',
            'port' => 993,
            'encryption' => MailEncryption::Ssl,
            'username' => 'support',
            'password' => 'factory-password',
            'folder' => 'INBOX',
            'is_active' => true,
        ];
    }
}
