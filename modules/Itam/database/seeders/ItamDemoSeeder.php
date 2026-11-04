<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Database\Seeders\DemoClock;
use Modules\Core\Database\Seeders\DemoCompany;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Itam\Application\Actions\AssignAsset;
use Modules\Itam\Application\Actions\ChangeAssetStatus;
use Modules\Itam\Application\Actions\ManageLicenseSeats;
use Modules\Itam\Application\Actions\ProvisionDefaultCategories;
use Modules\Itam\Application\Actions\SaveAsset;
use Modules\Itam\Application\Actions\SaveLicense;
use Modules\Itam\Domain\Enums\AssetStatus;
use Modules\Itam\Domain\Models\Category;

/**
 * The demo company's equipment: a laptop for everyone (handed over), monitors,
 * the servers, network and storage the CMDB describes (linked to their
 * items), a few in stock; licences per person, per device and site-wide, one
 * expiring soon. Keys are kept in DemoCompany::$refs ("itam.host_1").
 */
final class ItamDemoSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: int, 6: array<string, mixed>}> key => category, name, manufacturer, model, supplier, months old, properties */
    private const array INFRASTRUCTURE = [
        'host_1' => ['server', 'hv-01', 'Demo Hardware', 'SRV-2U', 'hardware', 30, ['hostname' => 'hv-01', 'cpu_cores' => 32, 'memory_gb' => 512, 'ip_address' => '10.10.1.11']],
        'host_2' => ['server', 'hv-02', 'Demo Hardware', 'SRV-2U', 'hardware', 30, ['hostname' => 'hv-02', 'cpu_cores' => 32, 'memory_gb' => 512, 'ip_address' => '10.10.1.12']],
        'backup_server' => ['server', 'bkp-01', 'Demo Hardware', 'SRV-1U', 'hardware', 46, ['hostname' => 'bkp-01', 'cpu_cores' => 8, 'memory_gb' => 64, 'ip_address' => '10.10.1.13']],
        'core_switch' => ['network_device', 'core-sw-01', 'Sample', 'SW-48', 'network', 58, ['device_type' => 'Switch', 'ip_address' => '10.10.0.2', 'firmware' => '9.3']],
        'access_switch' => ['network_device', 'acc-sw-01', 'Sample', 'SW-24', 'network', 24, ['device_type' => 'Switch', 'ip_address' => '10.10.0.3', 'firmware' => '9.3']],
        'firewall' => ['network_device', 'fw-01', 'Sample', 'FW-400', 'network', 20, ['device_type' => 'Firewall', 'ip_address' => '10.10.0.1', 'firmware' => '7.4']],
        'wh_ap_1' => ['network_device', 'wh-ap-01', 'Sample', 'AP-6', 'network', 14, ['device_type' => 'Access point', 'ip_address' => '10.20.0.11']],
        'wh_ap_2' => ['network_device', 'wh-ap-02', 'Sample', 'AP-6', 'network', 14, ['device_type' => 'Access point', 'ip_address' => '10.20.0.12']],
        'patch_panel' => ['network_device', 'pp-r02-01', 'Sample', 'PP-24', 'network', 58, ['device_type' => 'Other']],
        'wh_switch' => ['network_device', 'wh-sw-01', 'Sample', 'SW-24', 'network', 14, ['device_type' => 'Switch', 'ip_address' => '10.20.0.2', 'firmware' => '9.3']],
        'wh_patch_panel' => ['network_device', 'pp-clj-01', 'Sample', 'PP-24', 'network', 14, ['device_type' => 'Other']],
        'nas' => ['storage', 'nas-01', 'Demo Hardware', 'NS-96', 'hardware', 34, ['capacity_tb' => 96]],
        'floor2_printer' => ['printer', 'Floor 2 printer', 'Demo Hardware', 'MF-500', 'hardware', 40, ['printer_type' => 'Multifunction', 'ip_address' => '10.30.0.50']],
    ];

    /** Assets that are what a CMDB item describes. */
    private const array CMDB = ['host_1' => 'host_1', 'host_2' => 'host_2', 'core_switch' => 'core_switch', 'firewall' => 'firewall', 'nas' => 'nas', 'wh_ap_1' => 'wh_ap', 'wh_ap_2' => 'wh_ap'];

    public function run(DemoCompany $company, OrganizationContext $context, ProvisionDefaultCategories $categories, SaveAsset $save, AssignAsset $assign, ChangeAssetStatus $change, SaveLicense $licenses, ManageLicenseSeats $seats, ConfigurationItems $items): void
    {
        $context->runAs($company->organization, function () use ($company, $categories, $save, $assign, $change, $licenses, $seats, $items): void {
            $categories->handle();
            $category = fn (string $key): Category => Category::query()->where('key', $key)->sole();
            $today = DemoClock::today();
            $actor = $company->user('assets');
            $laptops = [];

            foreach (array_values($company->users) as $index => $person) {
                $bought = $today->subMonths(6 + ($index * 5) % 40);
                $laptop = $save->handle(null, $category('laptop'), [
                    'manufacturer' => 'Demo Hardware', 'model' => $index % 4 === 0 ? 'Laptop 16' : 'Laptop 14', 'serial_number' => sprintf('NW%08d', 4100 + $index),
                    'status' => 'in_stock', 'location_id' => $company->locations['office']->id, 'supplier_id' => $company->suppliers['hardware']->id,
                    'contract_id' => $company->contracts['hardware_support']->id, 'purchase_date' => $bought->toDateString(), 'purchase_cost' => '1150.00', 'currency' => 'EUR',
                    // Three-year warranty: the oldest ones end soon or have ended.
                    'warranty_ends_on' => $bought->addYears(3)->toDateString(),
                ], ['hostname' => 'LT-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), 'operating_system' => 'Windows 11', 'memory_gb' => 16, 'storage_gb' => 512]);
                $assign->handle($laptop, $person, $actor, $company->locations['office']->id, null, false);
                $laptops[] = $laptop;
            }

            foreach (range(1, 6) as $number) {
                $monitor = $save->handle(null, $category('monitor'), ['manufacturer' => 'Demo Hardware', 'model' => 'Monitor 27', 'serial_number' => sprintf('NWM%06d', $number), 'status' => 'in_stock', 'location_id' => $company->locations['office']->id, 'purchase_date' => $today->subMonths(10)->toDateString()], ['size_inches' => 27, 'resolution' => '2560x1440']);
                $company->refs['itam.monitor_'.$number] = $monitor->id;
            }

            foreach (self::INFRASTRUCTURE as $key => [$type, $name, $manufacturer, $model, $supplier, $age, $properties]) {
                $bought = $today->subMonths($age);
                $asset = $save->handle(null, $category($type), [
                    'name' => $name, 'manufacturer' => $manufacturer, 'model' => $model, 'serial_number' => strtoupper(substr($manufacturer, 0, 2)).sprintf('%07d', crc32($key) % 10000000),
                    'location_id' => $company->locations[str_starts_with($key, 'wh_') ? 'comms_room' : ($key === 'floor2_printer' ? 'office' : 'server_room')]->id,
                    'supplier_id' => $company->suppliers[$supplier]->id, 'contract_id' => $company->contracts[$supplier === 'network' ? 'network_support' : 'hardware_support']->id,
                    'purchase_date' => $bought->toDateString(), 'warranty_ends_on' => $bought->addYears(5)->toDateString(),
                ], $properties);
                // Saved assets arrive in stock; infrastructure is then put in service where it stands.
                $asset = $change->handle($asset, AssetStatus::Deployed, $actor);
                $company->refs['itam.'.$key] = $asset->id;

                if (isset(self::CMDB[$key])) {
                    $items->link($asset, $company->ref('cmdb.'.self::CMDB[$key]), $actor);
                }
            }

            $licence = fn (string $product, string $name, string $model, int $quantity, string $supplier, ?string $contract, int $expiresInDays, string $cost) => $licenses->handle(null, '', $product, [
                'name' => $name, 'model' => $model, 'quantity' => $quantity, 'supplier_id' => $company->suppliers[$supplier]->id,
                'contract_id' => $contract !== null ? $company->contracts[$contract]->id : null, 'purchase_date' => $today->subYear()->toDateString(),
                'cost' => $cost, 'currency' => 'EUR', 'starts_on' => $today->subYear()->toDateString(), 'expires_on' => $today->addDays($expiresInDays)->toDateString(),
            ]);

            $office = $licence('Office Suite', 'Office Suite – business plan', 'per_user', 25, 'cloud', 'cloud_subscription', 210, '4800.00');
            foreach (array_slice(array_values($company->users), 0, 18) as $person) {
                $seats->giveToPerson($office, $person, $actor);
            }

            $antivirus = $licence('Endpoint Protection', 'Endpoint Protection 2026', 'per_device', 30, 'hardware', null, 25, '1500.00');
            foreach (array_slice($laptops, 0, 15) as $laptop) {
                $seats->giveToDevice($antivirus, $laptop, $actor);
            }

            $licence('ERP Suite', 'ERP named users', 'per_user', 40, 'erp', 'erp_licence', 85, '22000.00');
        });
    }
}
