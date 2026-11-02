<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Itam\Domain\Models\Category;

/**
 * The categories every organization starts with, editable and extensible
 * afterwards. Created on first use, and only once: an organization that deleted
 * them does not get them back.
 */
final readonly class ProvisionDefaultCategories
{
    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
    ) {}

    public function handle(): void
    {
        $organizationId = $this->context->requireId();

        if (Category::query()->withTrashed()->exists()) {
            return;
        }

        $this->db->transaction(function () use ($organizationId): void {
            foreach (self::definitions() as $key => [$name, $description, $fields]) {
                $category = new Category;
                $category->forceFill([
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

        $computer = [$text('hostname', 'Hostname'), $text('operating_system', 'Operating system'), $text('cpu', 'Processor'), $number('memory_gb', 'Memory (GB)'), $number('storage_gb', 'Storage (GB)')];
        $mobile = [$text('imei', 'IMEI'), $text('operating_system', 'Operating system')];

        return [
            'laptop' => ['Laptop', 'Portable computers.', $computer],
            'desktop' => ['Desktop', 'Desktop computers and workstations.', $computer],
            'monitor' => ['Monitor', 'Displays.', [$number('size_inches', 'Size (inches)'), $text('resolution', 'Resolution')]],
            'phone' => ['Phone', 'Mobile phones.', $mobile],
            'tablet' => ['Tablet', 'Tablets.', $mobile],
            'printer' => ['Printer', 'Printers, scanners and multifunction devices.', [$choice('printer_type', 'Type', ['Laser', 'Inkjet', 'Multifunction', 'Label', 'Other']), $text('ip_address', 'IP address')]],
            'server' => ['Server', 'Physical servers.', [$text('hostname', 'Hostname'), $number('cpu_cores', 'CPU cores'), $number('memory_gb', 'Memory (GB)'), $text('ip_address', 'Management IP address')]],
            'network_device' => ['Network device', 'Switches, routers, firewalls, access points.', [$choice('device_type', 'Device type', ['Switch', 'Router', 'Firewall', 'Access point', 'Other']), $text('ip_address', 'Management IP address'), $text('firmware', 'Firmware version')]],
            'storage' => ['Storage', 'Storage arrays, NAS and backup appliances.', [$number('capacity_tb', 'Capacity (TB)')]],
            'peripheral' => ['Peripheral', 'Docking stations, keyboards, headsets and other accessories.', []],
        ];
    }
}
