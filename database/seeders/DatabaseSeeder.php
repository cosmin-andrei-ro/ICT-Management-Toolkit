<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use RuntimeException;

final class DatabaseSeeder extends Seeder
{
    /**
     * Demo data for local development only: the demo account has a well-known password.
     */
    public function run(SyncPermissions $syncPermissions, OrganizationContext $context): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo data may only be seeded in the local or testing environment.');
        }

        $syncPermissions->handle();

        $organization = Organization::factory()->create([
            'name' => 'Demo Organization',
            'slug' => 'demo',
        ]);

        $admin = User::factory()->memberOf($organization)->create([
            'name' => 'Demo Administrator',
            'email' => 'admin@example.com',
        ]);

        $context->runAs($organization, fn () => $admin->assignRole(DefaultRole::Administrator->value));
    }
}
