<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Database\Seeders\DemoClock;
use Modules\Core\Database\Seeders\DemoCompany;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Dcim\Application\Actions\AddPorts;
use Modules\Dcim\Application\Actions\ConnectPorts;
use Modules\Dcim\Application\Actions\ManageIpam;
use Modules\Dcim\Application\Actions\MountAsset;
use Modules\Dcim\Application\Actions\PlugIn;
use Modules\Dcim\Application\Actions\SavePowerSource;
use Modules\Dcim\Application\Actions\SaveRack;
use Modules\Dcim\Domain\Enums\AddressStatus;
use Modules\Dcim\Domain\Enums\CableStatus;
use Modules\Dcim\Domain\Enums\CableType;
use Modules\Dcim\Domain\Enums\MountFace;
use Modules\Dcim\Domain\Enums\PortKind;
use Modules\Dcim\Domain\Enums\PowerKind;
use Modules\Dcim\Domain\Enums\PowerSide;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\Port;
use Modules\Dcim\Domain\Models\PowerSource;
use Modules\Dcim\Domain\Models\Rack;

/**
 * The server room in Bucharest and the comms room in Cluj: six racks with
 * the ITAM equipment mounted, ports and cables (through patch panels), A/B
 * power through two UPSs - the warehouse switch is fed from one side only -
 * and the address plan, with the warehouse subnet almost full.
 */
final class DcimDemoSeeder extends Seeder
{
    /**
     * key => rack, U position, height, power draw (W), port series [pattern, kind, patch panel].
     *
     * @var array<string, array{0: string, 1: int, 2: int, 3: ?int, 4: list<array{0: string, 1: PortKind, 2: bool}>}>
     */
    private const array MOUNTS = [
        'nas' => ['BUC-R01', 2, 2, 450, [['eth[0-1]', PortKind::Rj45, false]]],
        'backup_server' => ['BUC-R01', 5, 1, 350, [['eth[0-1]', PortKind::Rj45, false]]],
        'host_1' => ['BUC-R01', 10, 2, 750, [['eth[0-3]', PortKind::Rj45, false]]],
        'host_2' => ['BUC-R01', 13, 2, 750, [['eth[0-3]', PortKind::Rj45, false]]],
        'patch_panel' => ['BUC-R02', 42, 1, null, [['[01-24]', PortKind::Rj45, true]]],
        'core_switch' => ['BUC-R02', 40, 1, 150, [['Gi1/0/[1-48]', PortKind::Rj45, false], ['Te1/1/[1-4]', PortKind::SfpPlus, false]]],
        'access_switch' => ['BUC-R02', 39, 1, 120, [['Gi1/0/[1-24]', PortKind::Rj45, false], ['Te1/1/[1-2]', PortKind::SfpPlus, false]]],
        'firewall' => ['BUC-R02', 37, 1, 60, [['port[1-8]', PortKind::Rj45, false]]],
        'wh_patch_panel' => ['CLJ-R01', 12, 1, null, [['[01-24]', PortKind::Rj45, true]]],
        'wh_switch' => ['CLJ-R01', 11, 1, 120, [['Gi1/0/[1-24]', PortKind::Rj45, false], ['Te1/1/[1-2]', PortKind::SfpPlus, false]]],
    ];

    /**
     * [device, port] to [device, port], type, colour, status.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: CableType, 5: string, 6: CableStatus}>
     */
    private const array CABLES = [
        ['host_1', 'eth0', 'core_switch', 'Gi1/0/1', CableType::Cat6a, 'blue', CableStatus::Connected],
        ['host_1', 'eth1', 'core_switch', 'Gi1/0/2', CableType::Cat6a, 'blue', CableStatus::Connected],
        ['host_2', 'eth0', 'core_switch', 'Gi1/0/3', CableType::Cat6a, 'blue', CableStatus::Connected],
        ['host_2', 'eth1', 'core_switch', 'Gi1/0/4', CableType::Cat6a, 'blue', CableStatus::Connected],
        ['backup_server', 'eth0', 'core_switch', 'Gi1/0/5', CableType::Cat6a, 'blue', CableStatus::Connected],
        ['nas', 'eth0', 'core_switch', 'Gi1/0/6', CableType::Cat6a, 'blue', CableStatus::Connected],
        ['nas', 'eth1', 'core_switch', 'Gi1/0/7', CableType::Cat6a, 'blue', CableStatus::Connected],
        ['firewall', 'port1', 'core_switch', 'Gi1/0/48', CableType::Cat6a, 'red', CableStatus::Connected],
        ['core_switch', 'Te1/1/1', 'access_switch', 'Te1/1/1', CableType::Dac, 'black', CableStatus::Connected],
        ['access_switch', 'Gi1/0/1', 'patch_panel', '01', CableType::Cat6, 'grey', CableStatus::Connected],
        ['access_switch', 'Gi1/0/2', 'patch_panel', '02', CableType::Cat6, 'grey', CableStatus::Connected],
        ['host_2', 'eth2', 'core_switch', 'Gi1/0/8', CableType::Cat6a, 'blue', CableStatus::Planned],
        ['wh_switch', 'Gi1/0/1', 'wh_patch_panel', '01', CableType::Cat6, 'grey', CableStatus::Connected],
        ['wh_switch', 'Gi1/0/2', 'wh_patch_panel', '02', CableType::Cat6, 'grey', CableStatus::Connected],
    ];

    public function run(DemoCompany $company, OrganizationContext $context, SaveRack $racks, MountAsset $mount, AddPorts $ports, ConnectPorts $cables, SavePowerSource $power, PlugIn $plug, ManageIpam $ipam): void
    {
        $context->runAs($company->organization, function () use ($company, $racks, $mount, $ports, $cables, $power, $plug, $ipam): void {
            $actor = $company->user('assets');

            DemoClock::at(DemoClock::daysAgo(89, '09:00'));
            $rack = $this->racks($company, $racks);
            $mounted = [];

            foreach (self::MOUNTS as $key => [$rackName, $position, $height, $watts, $series]) {
                $mounted[$key] = $mount->handle($rack[$rackName], null, $company->ref('itam.'.$key), $position, $height, MountFace::Front, $watts !== null, $watts, null, $actor);

                foreach ($series as [$pattern, $kind, $patchPanel]) {
                    $ports->handle($mounted[$key], $pattern, $kind, $patchPanel ? MountFace::Front : MountFace::Rear, $patchPanel);
                }
            }

            foreach (self::CABLES as $number => [$fromDevice, $fromPort, $toDevice, $toPort, $type, $colour, $status]) {
                $cables->connect(self::port($mounted, $fromDevice, $fromPort), self::port($mounted, $toDevice, $toPort), [
                    'label' => sprintf('C-%03d', $number + 1), 'type' => $type, 'colour' => $colour,
                    'length_m' => $type === CableType::Dac ? '1.0' : '2.0', 'status' => $status, 'notes' => null,
                ]);
            }

            $this->power($company, $rack, $mounted, $power, $plug);
            $this->addresses($company, $mounted, $ipam);
        });

        DemoClock::reset();
    }

    /**
     * @param  array<string, Mount>  $mounted
     */
    private static function port(array $mounted, string $device, string $name): Port
    {
        return Port::query()->where('mount_id', $mounted[$device]->id)->where('name', $name)->sole();
    }

    /**
     * Four racks in the Bucharest server room, two small ones in the Cluj comms room.
     *
     * @return array<string, Rack>
     */
    private function racks(DemoCompany $company, SaveRack $racks): array
    {
        $serverRoom = $company->locations['server_room']->id;
        $commsRoom = $company->locations['comms_room']->id;
        $saved = [];

        foreach ([
            'BUC-R01' => [$serverRoom, 'A', 1, 42, 5000, 'Servers and storage'],
            'BUC-R02' => [$serverRoom, 'A', 2, 42, 2000, 'Network'],
            'BUC-R03' => [$serverRoom, 'A', 3, 42, 5000, 'Reserved for the second storage array'],
            'BUC-R04' => [$serverRoom, 'B', 1, 42, null, 'Spare'],
            'CLJ-R01' => [$commsRoom, null, 1, 12, 1000, 'Warehouse network'],
            'CLJ-R02' => [$commsRoom, null, 2, 12, null, 'Telephony and alarm'],
        ] as $name => [$location, $row, $position, $height, $maxPower, $notes]) {
            $saved[$name] = $racks->handle(null, ['location_id' => $location, 'name' => $name, 'row' => $row, 'position' => $position, 'height_u' => $height, 'max_power_watts' => $maxPower, 'notes' => $notes]);
        }

        return $saved;
    }

    /**
     * Two feeds through two UPSs to an A and a B PDU per rack in Bucharest;
     * one feed and one UPS in Cluj.
     *
     * @param  array<string, Rack>  $rack
     * @param  array<string, Mount>  $mounted
     */
    private function power(DemoCompany $company, array $rack, array $mounted, SavePowerSource $power, PlugIn $plug): void
    {
        $serverRoom = $company->locations['server_room']->id;
        $commsRoom = $company->locations['comms_room']->id;
        $source = fn (string $location, PowerKind $kind, string $name, ?PowerSide $side, ?PowerSource $upstream, ?Rack $inRack, ?int $capacity): PowerSource => $power->handle(null, [
            'location_id' => $location, 'kind' => $kind, 'name' => $name, 'side' => $side, 'upstream_id' => $upstream?->id,
            'asset_id' => null, 'rack_id' => $inRack?->id, 'capacity_watts' => $capacity, 'notes' => null,
        ]);

        $pdus = [];

        foreach ([PowerSide::A, PowerSide::B] as $side) {
            $letter = strtoupper($side->value);
            $feed = $source($serverRoom, PowerKind::Feed, 'Grid feed '.$letter, $side, null, null, null);
            $ups = $source($serverRoom, PowerKind::Ups, 'UPS-'.$letter, $side, $feed, null, 6000);
            $pdus['BUC-R01'][$letter] = $source($serverRoom, PowerKind::Pdu, 'PDU-R01-'.$letter, $side, $ups, $rack['BUC-R01'], 3700);
            $pdus['BUC-R02'][$letter] = $source($serverRoom, PowerKind::Pdu, 'PDU-R02-'.$letter, $side, $ups, $rack['BUC-R02'], 3700);
        }

        $warehouseFeed = $source($commsRoom, PowerKind::Feed, 'Cluj grid feed', null, null, null, null);
        $warehouseUps = $source($commsRoom, PowerKind::Ups, 'UPS-CLJ', null, $warehouseFeed, null, 1000);
        $warehousePdu = $source($commsRoom, PowerKind::Pdu, 'PDU-CLJ-R01', null, $warehouseUps, $rack['CLJ-R01'], 1000);

        $outlet = [];

        foreach (self::MOUNTS as $key => [$rackName, , , $watts]) {
            if ($watts === null) {
                continue;
            }

            // The warehouse switch has a single power supply: the one device fed from one side.
            foreach (isset($pdus[$rackName]) ? $pdus[$rackName] : ['A' => $warehousePdu] as $pdu) {
                $outlet[$pdu->id] = ($outlet[$pdu->id] ?? 0) + 1;
                $plug->handle($mounted[$key], $pdu, (string) $outlet[$pdu->id]);
            }
        }
    }

    /**
     * VLANs, subnets and the addresses of the mounted devices and of the virtual machines (CMDB items).
     *
     * @param  array<string, Mount>  $mounted
     */
    private function addresses(DemoCompany $company, array $mounted, ManageIpam $ipam): void
    {
        $hq = $company->locations['hq']->id;
        $warehouse = $company->locations['warehouse']->id;
        $vlan = fn (int $vid, string $name, string $location) => $ipam->saveVlan(null, ['vid' => $vid, 'name' => $name, 'location_id' => $location, 'description' => null]);
        $subnet = fn (string $network, string $gateway, string $vlanId, string $location, string $purpose) => $ipam->saveSubnet(null, ['network' => $network, 'gateway' => $gateway, 'vlan_id' => $vlanId, 'location_id' => $location, 'purpose' => $purpose, 'notes' => null]);

        $subnet('10.10.0.0/24', '10.10.0.1', $vlan(99, 'Management', $hq)->id, $hq, 'Network device management');
        $subnet('10.10.1.0/24', '10.10.1.1', $vlan(10, 'Servers', $hq)->id, $hq, 'Servers and virtual machines');
        $subnet('10.30.0.0/24', '10.30.0.1', $vlan(30, 'Office', $hq)->id, $hq, 'Office computers and printers');
        $subnet('10.20.0.0/28', '10.20.0.1', $vlan(100, 'Warehouse', $warehouse)->id, $warehouse, 'Warehouse switch, access points and scanners');

        $address = fn (string $host, AddressStatus $status, ?string $dns, ?string $mountId, ?string $itemId, ?string $description = null) => $ipam->saveAddress(null, [
            'address' => $host, 'status' => $status, 'dns_name' => $dns, 'mount_id' => $mountId, 'configuration_item_id' => $itemId, 'description' => $description,
        ]);

        foreach (['firewall' => '10.10.0.1', 'core_switch' => '10.10.0.2', 'access_switch' => '10.10.0.3', 'host_1' => '10.10.1.11', 'host_2' => '10.10.1.12', 'backup_server' => '10.10.1.13', 'nas' => '10.10.1.14', 'wh_switch' => '10.20.0.2'] as $key => $host) {
            $address($host, AddressStatus::Active, str_replace('_', '-', $key).'.demo-logistics.example', $mounted[$key]->id, null);
        }

        foreach (['vm_erp_app' => '10.10.1.21', 'vm_erp_db' => '10.10.1.22', 'vm_wms' => '10.10.1.23', 'vm_file' => '10.10.1.24'] as $key => $host) {
            $address($host, AddressStatus::Active, str_replace('_', '-', $key).'.demo-logistics.example', null, $company->ref('cmdb.'.$key));
        }

        $address('10.20.0.11', AddressStatus::Active, 'wh-ap-01.demo-logistics.example', null, $company->ref('cmdb.wh_ap'));
        $address('10.20.0.12', AddressStatus::Active, 'wh-ap-02.demo-logistics.example', null, $company->ref('cmdb.wh_ap'));
        $address('10.30.0.50', AddressStatus::Reserved, 'floor2-printer.demo-logistics.example', null, null, 'Floor 2 printer');

        // Hand scanners take what is left of the warehouse subnet: it is nearly full.
        foreach (range(3, 10) as $last) {
            $address('10.20.0.'.$last, AddressStatus::Dhcp, null, null, null, 'Hand scanner pool');
        }
    }
}
