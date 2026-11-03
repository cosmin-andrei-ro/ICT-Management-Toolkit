<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;
use Modules\Applications\Database\Seeders\ApplicationsDemoSeeder;
use Modules\Audit\Database\Seeders\AuditDemoSeeder;
use Modules\Bcm\Database\Seeders\BcmDemoSeeder;
use Modules\Bia\Database\Seeders\BiaDemoSeeder;
use Modules\Cmdb\Database\Seeders\CmdbDemoSeeder;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Database\Seeders\CoreDemoSeeder;
use Modules\Core\Database\Seeders\DemoClock;
use Modules\Core\Database\Seeders\DemoCompany;
use Modules\Core\Domain\Models\Organization;
use Modules\Dcim\Database\Seeders\DcimDemoSeeder;
use Modules\Documentation\Database\Seeders\DocumentationDemoSeeder;
use Modules\Itam\Database\Seeders\ItamDemoSeeder;
use Modules\Projects\Database\Seeders\ProjectsDemoSeeder;
use Modules\Reporting\Database\Seeders\ReportingDemoSeeder;
use Modules\Risk\Database\Seeders\RiskDemoSeeder;
use Modules\Risk\Database\Seeders\RiskSuggestionsDemoSeeder;
use Modules\Ticketing\Database\Seeders\TicketingDemoSeeder;
use RuntimeException;

final class DatabaseSeeder extends Seeder
{
    /**
     * The modules' demo seeders, in order: each builds on what the previous
     * ones created.
     *
     * @var list<class-string<Seeder>>
     */
    private const array MODULES = [
        TicketingDemoSeeder::class,
        CmdbDemoSeeder::class,
        ItamDemoSeeder::class,
        BiaDemoSeeder::class,
        ApplicationsDemoSeeder::class,
        BcmDemoSeeder::class,
        RiskDemoSeeder::class,
        AuditDemoSeeder::class,
        DocumentationDemoSeeder::class,
        DcimDemoSeeder::class,
        ProjectsDemoSeeder::class,
        RiskSuggestionsDemoSeeder::class,
        ReportingDemoSeeder::class,
    ];

    /**
     * Demo data for local development only: every demo account has a
     * well-known password and two-factor secret (README, "Demo data").
     */
    public function run(SyncPermissions $syncPermissions): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo data may only be seeded in the local or testing environment.');
        }

        $syncPermissions->handle();
        // Nobody should receive e-mails about three months of invented history.
        Notification::fake();

        $organization = Organization::factory()->create([
            'name' => 'Demo Logistics',
            'slug' => 'demo',
        ]);

        /** @var DemoCompany $company */
        $company = app()->call([app(CoreDemoSeeder::class), 'run'], ['organization' => $organization]);

        foreach (self::MODULES as $seeder) {
            app()->call([app($seeder), 'run'], ['company' => $company]);
            DemoClock::reset();
        }
    }
}
