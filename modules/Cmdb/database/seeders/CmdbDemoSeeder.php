<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Database\Seeders;

use Illuminate\Database\Seeder;
use LogicException;
use Modules\Cmdb\Application\Actions\ManageRelationships;
use Modules\Cmdb\Application\Actions\ProvisionDefaultTypes;
use Modules\Cmdb\Application\Actions\SaveConfigurationItem;
use Modules\Cmdb\Domain\Enums\RelationshipType;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Core\Database\Seeders\DemoCompany;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * The IT estate of the demo company and what depends on what: business
 * services, applications, virtual machines on two hosts, databases, the
 * network and storage. Keys are kept in DemoCompany::$refs ("cmdb.erp").
 */
final class CmdbDemoSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: array<string, mixed>}> key => type, name, criticality, team, properties */
    private const array ITEMS = [
        'svc_invoicing' => ['business_service', 'Invoicing', 'critical', 'applications', ['service_hours' => 'Mon–Fri 08:00–18:00', 'users' => 25]],
        'svc_warehouse' => ['business_service', 'Warehouse operations', 'critical', 'applications', ['service_hours' => '24/7', 'users' => 60]],
        'svc_email' => ['business_service', 'E-mail and collaboration', 'high', 'desk', ['service_hours' => '24/7', 'users' => 140]],
        'svc_payroll' => ['business_service', 'Payroll', 'high', 'applications', ['service_hours' => 'Mon–Fri 09:00–17:00', 'users' => 6]],
        'erp' => ['application', 'ERP', 'critical', 'applications', ['version' => '12.4', 'vendor' => 'Ledger Soft', 'hosting' => 'On-premises']],
        'wms' => ['application', 'Warehouse management system', 'critical', 'applications', ['version' => '5.1', 'vendor' => 'In-house', 'hosting' => 'On-premises']],
        'mail' => ['cloud_service', 'Mail and calendar tenant', 'high', 'desk', ['provider' => 'Blue Sky Cloud', 'region' => 'EU West']],
        'hr_portal' => ['application', 'HR portal', 'medium', 'applications', ['version' => '3.0', 'vendor' => 'Blue Sky Cloud', 'hosting' => 'SaaS']],
        'host_1' => ['server', 'hv-01', 'critical', 'infrastructure', ['operating_system' => 'Hypervisor 8', 'cpu_cores' => 32, 'memory_gb' => 512, 'ip_address' => '10.10.10.11']],
        'host_2' => ['server', 'hv-02', 'critical', 'infrastructure', ['operating_system' => 'Hypervisor 8', 'cpu_cores' => 32, 'memory_gb' => 512, 'ip_address' => '10.10.10.12']],
        'vm_erp_app' => ['virtual_machine', 'erp-app-01', 'critical', 'infrastructure', ['operating_system' => 'Linux', 'cpu_cores' => 8, 'memory_gb' => 32, 'ip_address' => '10.10.20.21']],
        'vm_erp_db' => ['virtual_machine', 'erp-db-01', 'critical', 'infrastructure', ['operating_system' => 'Linux', 'cpu_cores' => 16, 'memory_gb' => 128, 'ip_address' => '10.10.20.22']],
        'vm_wms' => ['virtual_machine', 'wms-app-01', 'critical', 'infrastructure', ['operating_system' => 'Linux', 'cpu_cores' => 8, 'memory_gb' => 32, 'ip_address' => '10.10.20.31']],
        'vm_file' => ['virtual_machine', 'file-01', 'medium', 'infrastructure', ['operating_system' => 'Windows Server', 'cpu_cores' => 4, 'memory_gb' => 16, 'ip_address' => '10.10.20.41']],
        'db_erp' => ['database', 'ERP database', 'critical', 'infrastructure', ['engine' => 'Relational', 'version' => '16', 'size_gb' => 420]],
        'db_wms' => ['database', 'WMS database', 'critical', 'infrastructure', ['engine' => 'Relational', 'version' => '16', 'size_gb' => 180]],
        'core_switch' => ['network_device', 'core-sw-01', 'critical', 'infrastructure', ['device_type' => 'Switch', 'ip_address' => '10.10.0.2', 'firmware' => '9.3']],
        'firewall' => ['network_device', 'fw-01', 'critical', 'infrastructure', ['device_type' => 'Firewall', 'ip_address' => '10.10.0.1', 'firmware' => '7.4']],
        'wh_ap' => ['network_device', 'Warehouse access points', 'high', 'infrastructure', ['device_type' => 'Access point', 'ip_address' => '10.20.0.10']],
        'nas' => ['storage', 'nas-01', 'high', 'infrastructure', ['capacity_tb' => 96]],
    ];

    /** @var list<array{0: string, 1: RelationshipType, 2: string}> */
    private const array RELATIONS = [
        ['svc_invoicing', RelationshipType::DependsOn, 'erp'],
        ['svc_payroll', RelationshipType::DependsOn, 'erp'],
        ['svc_warehouse', RelationshipType::DependsOn, 'wms'],
        ['svc_warehouse', RelationshipType::DependsOn, 'wh_ap'],
        ['svc_email', RelationshipType::DependsOn, 'mail'],
        ['erp', RelationshipType::RunsOn, 'vm_erp_app'],
        ['erp', RelationshipType::Uses, 'db_erp'],
        ['wms', RelationshipType::RunsOn, 'vm_wms'],
        ['wms', RelationshipType::Uses, 'db_wms'],
        ['db_erp', RelationshipType::RunsOn, 'vm_erp_db'],
        ['db_wms', RelationshipType::RunsOn, 'vm_erp_db'],
        ['vm_erp_app', RelationshipType::RunsOn, 'host_1'],
        ['vm_erp_db', RelationshipType::RunsOn, 'host_2'],
        ['vm_wms', RelationshipType::RunsOn, 'host_1'],
        ['vm_file', RelationshipType::RunsOn, 'host_2'],
        ['vm_file', RelationshipType::Uses, 'nas'],
        ['host_1', RelationshipType::ConnectsTo, 'core_switch'],
        ['host_2', RelationshipType::ConnectsTo, 'core_switch'],
        ['core_switch', RelationshipType::ConnectsTo, 'firewall'],
        ['wh_ap', RelationshipType::ConnectsTo, 'firewall'],
    ];

    public function run(DemoCompany $company, OrganizationContext $context, ProvisionDefaultTypes $types, SaveConfigurationItem $save, ManageRelationships $relationships): void
    {
        $context->runAs($company->organization, function () use ($company, $types, $save, $relationships): void {
            $types->handle();
            $items = [];

            foreach (self::ITEMS as $key => [$type, $name, $criticality, $team, $properties]) {
                $items[$key] = $save->handle(null, CiType::query()->where('key', $type)->sole(), [
                    'name' => $name,
                    'status' => 'in_use',
                    'environment' => 'production',
                    'criticality' => $criticality,
                    'owner_id' => $company->user($team === 'applications' ? 'applications' : 'teamlead')->id,
                    'team_id' => $company->teams[$team]->id,
                ], $properties);
                $company->refs['cmdb.'.$key] = $items[$key]->id;
            }

            foreach (self::RELATIONS as [$source, $type, $target]) {
                $relationships->add($items[$source] ?? throw new LogicException($source), $type, $items[$target] ?? throw new LogicException($target));
            }

        });
    }
}
