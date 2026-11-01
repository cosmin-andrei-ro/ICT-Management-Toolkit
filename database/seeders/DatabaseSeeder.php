<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use RuntimeException;

final class DatabaseSeeder extends Seeder
{
    /**
     * Demo data for local development only: the demo account has a well-known password.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo data may only be seeded in the local or testing environment.');
        }

        $organization = Organization::factory()->create([
            'name' => 'Demo Organization',
            'slug' => 'demo',
        ]);

        User::factory()->memberOf($organization)->create([
            'name' => 'Demo Administrator',
            'email' => 'admin@example.com',
        ]);
    }
}
