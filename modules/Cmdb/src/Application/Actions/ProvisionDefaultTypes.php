<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * The types every organization starts with (owner's decision), editable and
 * extensible by administrators afterwards. Created on first use of the CMDB, and
 * only once: an organization that deleted them does not get them back.
 */
final readonly class ProvisionDefaultTypes
{
    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
    ) {}

    public function handle(): void
    {
        $organizationId = $this->context->requireId();

        if (CiType::query()->withTrashed()->exists()) {
            return;
        }

        $this->db->transaction(function () use ($organizationId): void {
            foreach (self::definitions() as $key => [$name, $description, $fields]) {
                $type = new CiType;
                $type->forceFill([
                    'organization_id' => $organizationId,
                    'key' => $key,
                    'name' => $name,
                    'description' => $description,
                    'attribute_schema' => $fields,
                ])->save();
            }
        });
    }

    /**
     * @return array<string, array{string, string, list<array<string, mixed>>}>
     */
    public static function definitions(): array
    {
        $text = fn (string $key, string $label, ?string $help = null): array => ['key' => $key, 'label' => $label, 'type' => 'text', 'help' => $help];
        $number = fn (string $key, string $label): array => ['key' => $key, 'label' => $label, 'type' => 'number'];
        $choice = fn (string $key, string $label, array $options): array => ['key' => $key, 'label' => $label, 'type' => 'select', 'options' => $options];

        $hardware = [$text('manufacturer', 'Manufacturer'), $text('model', 'Model'), $text('serial_number', 'Serial number')];

        return [
            'business_service' => ['Business service', 'What the business uses: e-mail, payroll, the online shop.', [$text('service_hours', 'Service hours', 'For example Monday–Friday 08:00–18:00.'), $number('users', 'Number of users')]],
            'application' => ['Application', 'Software the organization runs or subscribes to.', [$text('version', 'Version'), $text('vendor', 'Vendor'), $text('url', 'Address (URL)'), $choice('hosting', 'Hosting', ['On-premises', 'SaaS', 'Hybrid'])]],
            'server' => ['Physical server', 'A physical machine in a datacenter or server room.', [...$hardware, $text('operating_system', 'Operating system'), $number('cpu_cores', 'CPU cores'), $number('memory_gb', 'Memory (GB)'), $text('ip_address', 'IP address')]],
            'virtual_machine' => ['Virtual machine', 'A virtual server on a hypervisor or in the cloud.', [$text('operating_system', 'Operating system'), $number('cpu_cores', 'vCPUs'), $number('memory_gb', 'Memory (GB)'), $text('ip_address', 'IP address'), $text('hypervisor', 'Hypervisor or cluster')]],
            'database' => ['Database', 'A database instance or schema.', [$choice('engine', 'Engine', ['PostgreSQL', 'Microsoft SQL Server', 'Oracle', 'MySQL / MariaDB', 'Other']), $text('version', 'Version'), $number('size_gb', 'Size (GB)')]],
            'network_device' => ['Network device', 'Switches, routers, firewalls, access points, load balancers.', [$choice('device_type', 'Device type', ['Switch', 'Router', 'Firewall', 'Access point', 'Load balancer', 'Other']), ...$hardware, $text('ip_address', 'Management IP address'), $text('firmware', 'Firmware version')]],
            'storage' => ['Storage', 'Storage arrays, NAS and backup appliances.', [...$hardware, $number('capacity_tb', 'Capacity (TB)')]],
            'cloud_service' => ['Cloud service', 'A cloud resource or subscription: a tenant, a bucket, a managed database.', [$text('provider', 'Provider'), $text('region', 'Region'), $text('account', 'Account or subscription')]],
        ];
    }
}
