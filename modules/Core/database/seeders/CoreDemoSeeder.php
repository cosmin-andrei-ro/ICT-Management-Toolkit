<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Application\Contracts\SaveContract;
use Modules\Core\Application\Teams\SyncTeamMembers;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Department;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * "Demo Logistics", a fictional company: one account for each role (password
 * "password", the shared demo two-factor secret), employees, departments,
 * IT teams, two sites with a server room, suppliers and their contracts.
 * Names are invented.
 */
final class CoreDemoSeeder extends Seeder
{
    /** Shared by every demo account; never used outside local and testing. */
    public const string TWO_FACTOR_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    /** @var array<string, array{0: string, 1: string, 2: ?DefaultRole}> key => name, e-mail, role */
    private const array PEOPLE = [
        'admin' => ['Demo Administrator', 'admin@example.com', DefaultRole::Administrator],
        'agent' => ['Andrei Pop', 'agent@example.com', DefaultRole::Agent],
        'agent2' => ['Ioana Marin', 'agent2@example.com', DefaultRole::Agent],
        'teamlead' => ['Radu Stan', 'teamlead@example.com', DefaultRole::TeamLead],
        'cab' => ['Elena Dobre', 'cab@example.com', DefaultRole::CabMember],
        'assets' => ['Mihai Toma', 'assets@example.com', DefaultRole::AssetManager],
        'continuity' => ['Carmen Vlad', 'continuity@example.com', DefaultRole::ContinuityCoordinator],
        'applications' => ['Dan Ilie', 'applications@example.com', DefaultRole::ApplicationManager],
        'security' => ['Sorin Matei', 'security@example.com', DefaultRole::SecurityOfficer],
        'risk' => ['Laura Neagu', 'risk@example.com', DefaultRole::RiskManager],
        'compliance' => ['Bianca Stoica', 'compliance@example.com', DefaultRole::ComplianceManager],
        'auditor' => ['Victor Lazar', 'auditor@example.com', DefaultRole::Auditor],
        'projects' => ['Cristina Ene', 'projects@example.com', DefaultRole::ProjectManager],
        'employee' => ['Ana Georgescu', 'employee@example.com', DefaultRole::Requester],
        'employee_finance' => ['George Munteanu', 'finance@example.com', DefaultRole::Requester],
        'employee_logistics' => ['Paula Rusu', 'logistics@example.com', DefaultRole::Requester],
        'employee_hr' => ['Irina Dinu', 'hr@example.com', DefaultRole::Requester],
        'employee_sales' => ['Tudor Barbu', 'sales@example.com', DefaultRole::Requester],
        'employee_warehouse' => ['Ovidiu Nistor', 'warehouse@example.com', DefaultRole::Requester],
    ];

    public function run(Organization $organization, OrganizationContext $context, SyncTeamMembers $teams, SaveContract $contracts): DemoCompany
    {
        $company = new DemoCompany($organization);

        foreach (self::PEOPLE as $key => [$name, $email, $role]) {
            $factory = User::factory()->memberOf($organization);
            $user = ($role === DefaultRole::Requester ? $factory : $factory->withTwoFactor())->create(['name' => $name, 'email' => $email]);
            $context->runAs($organization, fn () => $user->assignRole($role->value));
            $company->users[$key] = User::query()->findOrFail($user->id);
        }

        $context->runAs($organization, function () use ($company, $teams, $contracts): void {
            foreach (['it' => ['Information Technology', 'IT', 'teamlead'], 'finance' => ['Finance', 'FIN', 'employee_finance'], 'logistics' => ['Logistics', 'LOG', 'employee_logistics'], 'hr' => ['Human Resources', 'HR', 'employee_hr'], 'sales' => ['Sales', 'SAL', 'employee_sales']] as $key => [$name, $code, $manager]) {
                $company->departments[$key] = Department::query()->create(['name' => $name, 'code' => $code, 'manager_id' => $company->user($manager)->id]);
            }

            foreach (['desk' => ['Service Desk', 'teamlead', ['agent', 'agent2', 'teamlead']], 'infrastructure' => ['Infrastructure', 'teamlead', ['teamlead', 'assets', 'agent2']], 'applications' => ['Business Applications', 'applications', ['applications', 'agent']]] as $key => [$name, $lead, $members]) {
                $team = Team::query()->create(['name' => $name, 'lead_id' => $company->user($lead)->id]);
                $teams->handle($team, array_map(fn (string $member): string => $company->user($member)->id, $members));
                $company->teams[$key] = $team;
            }

            $hq = Location::query()->create(['name' => 'Bucharest HQ', 'kind' => 'site', 'code' => 'BUC', 'address' => 'Example Street 1, Bucharest']);
            $building = Location::query()->create(['name' => 'Building A', 'kind' => 'building', 'parent_id' => $hq->id]);
            $company->locations = [
                'hq' => $hq,
                'building' => $building,
                'office' => Location::query()->create(['name' => 'Floor 2 offices', 'kind' => 'floor', 'parent_id' => $building->id]),
                'server_room' => Location::query()->create(['name' => 'Server room', 'kind' => 'room', 'code' => 'BUC-SR', 'parent_id' => $building->id]),
                'warehouse' => $warehouse = Location::query()->create(['name' => 'Cluj warehouse', 'kind' => 'site', 'code' => 'CLJ', 'address' => 'Example Road 10, Cluj-Napoca']),
                'comms_room' => Location::query()->create(['name' => 'Comms room', 'kind' => 'room', 'code' => 'CLJ-CR', 'parent_id' => $warehouse->id]),
            ];

            foreach ([
                'hardware' => ['Northwind Hardware', 'support@northwind-hardware.example', '+40 21 000 0001', 'Mon–Fri 08:00–18:00'],
                'network' => ['Acme Networks', 'noc@acme-networks.example', '+40 21 000 0002', '24/7'],
                'cloud' => ['Blue Sky Cloud', 'help@bluesky-cloud.example', null, '24/7'],
                'erp' => ['Ledger Soft', 'support@ledgersoft.example', '+40 21 000 0004', 'Mon–Fri 09:00–17:00'],
                'power' => ['Volt Services', 'service@volt-services.example', '+40 21 000 0005', '24/7 for emergencies'],
            ] as $key => [$name, $email, $phone, $hours]) {
                $company->suppliers[$key] = Supplier::query()->create(['name' => $name, 'support_email' => $email, 'support_phone' => $phone, 'support_hours' => $hours]);
            }

            $today = DemoClock::today();
            foreach ([
                'hardware_support' => ['hardware', 'Server and laptop support', 'support', $today->subYears(2), $today->addMonths(10), '24000.00'],
                'network_support' => ['network', 'Network maintenance', 'support', $today->subYear(), $today->addDays(40), '18000.00'],
                'cloud_subscription' => ['cloud', 'Cloud hosting and backup', 'subscription', $today->subMonths(8), $today->addMonths(16), '36000.00'],
                'erp_licence' => ['erp', 'ERP licences and support', 'license', $today->subYears(3), $today->addMonths(3), '52000.00'],
                'ups_maintenance' => ['power', 'UPS maintenance', 'support', $today->subMonths(6), $today->addMonths(6), '6000.00'],
            ] as $key => [$supplier, $title, $type, $start, $end, $value]) {
                $company->contracts[$key] = $contracts->handle(null, [
                    'supplier_id' => $company->suppliers[$supplier]->id, 'title' => $title, 'reference' => 'C-'.strtoupper(substr($key, 0, 3)).'-'.$start->year,
                    'type' => $type, 'start_date' => $start->toDateString(), 'end_date' => $end->toDateString(), 'auto_renews' => false, 'notice_days' => 60,
                    'value' => $value, 'currency' => 'EUR', 'billing_period' => 'yearly', 'owner_id' => $company->user('teamlead')->id,
                ]);
            }
        });

        return $company;
    }
}
