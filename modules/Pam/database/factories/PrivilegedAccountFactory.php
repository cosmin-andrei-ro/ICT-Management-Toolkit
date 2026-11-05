<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Pam\Domain\Enums\AccountType;
use Modules\Pam\Domain\Models\PrivilegedAccount;

/**
 * @extends Factory<PrivilegedAccount>
 */
final class PrivilegedAccountFactory extends Factory
{
    protected $model = PrivilegedAccount::class;

    public function definition(): array
    {
        return [
            'organization_id' => app(OrganizationContext::class)->id(),
            'name' => fake()->unique()->words(3, true),
            'username' => 'svc_'.fake()->unique()->lexify('????'),
            'type' => AccountType::Service,
        ];
    }
}
