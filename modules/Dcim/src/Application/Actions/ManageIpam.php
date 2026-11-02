<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Application\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\Location;
use Modules\Dcim\Domain\Enums\AddressStatus;
use Modules\Dcim\Domain\IpNetwork;
use Modules\Dcim\Domain\Models\IpAddress;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\Subnet;
use Modules\Dcim\Domain\Models\Vlan;

/**
 * VLANs, subnets and addresses (ADR 0050). The database refuses a second
 * record of a network or an address, a gateway outside its network and a
 * range stored as an address; these checks come first so people read why.
 */
final readonly class ManageIpam
{
    public function __construct(private ConfigurationItems $items) {}

    /**
     * @param  array{vid: int, name: string, location_id: ?string, description: ?string}  $data
     */
    public function saveVlan(?Vlan $vlan, array $data): Vlan
    {
        if ($data['location_id'] !== null && ! Location::query()->whereKey($data['location_id'])->exists()) {
            throw ValidationException::withMessages(['vlanLocationId' => __('dcim::racks.errors.location')]);
        }

        $taken = Vlan::query()->where('vid', $data['vid'])
            ->where(fn ($query) => $data['location_id'] === null ? $query->whereNull('location_id') : $query->where('location_id', $data['location_id']))
            ->when($vlan !== null, fn ($query) => $query->whereKeyNot($vlan?->id))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['vid' => __('dcim::ipam.errors.vid_taken')]);
        }

        $vlan ??= new Vlan;
        $vlan->forceFill($data)->save();

        return $vlan;
    }

    public function deleteVlan(Vlan $vlan): void
    {
        if ($vlan->subnets()->exists()) {
            throw ValidationException::withMessages(['vlans' => __('dcim::ipam.errors.vlan_in_use', ['vlan' => $vlan->label()])]);
        }

        $vlan->delete();
    }

    /**
     * @param  array{network: string, gateway: ?string, vlan_id: ?string, location_id: ?string, purpose: ?string, notes: ?string}  $data
     */
    public function saveSubnet(?Subnet $subnet, array $data): Subnet
    {
        $network = IpNetwork::parse($data['network']);

        if ($network === null) {
            throw ValidationException::withMessages(['network' => __('dcim::ipam.errors.network')]);
        }

        if ($network->hadHostBits) {
            throw ValidationException::withMessages(['network' => __('dcim::ipam.errors.host_bits', ['network' => $network->cidr()])]);
        }

        if (Subnet::query()->whereRaw('network = ?::cidr', [$network->cidr()])->when($subnet !== null, fn ($query) => $query->whereKeyNot($subnet?->id))->exists()) {
            throw ValidationException::withMessages(['network' => __('dcim::ipam.errors.network_taken')]);
        }

        $gateway = null;

        if ($data['gateway'] !== null) {
            $gateway = IpNetwork::address($data['gateway']);

            if ($gateway === null || ! $this->inside($gateway, $network->cidr())) {
                throw ValidationException::withMessages(['gateway' => __('dcim::ipam.errors.gateway', ['network' => $network->cidr()])]);
            }
        }

        if ($data['vlan_id'] !== null && ! Vlan::query()->whereKey($data['vlan_id'])->exists()) {
            throw ValidationException::withMessages(['vlanId' => __('dcim::ipam.errors.vlan')]);
        }

        if ($data['location_id'] !== null && ! Location::query()->whereKey($data['location_id'])->exists()) {
            throw ValidationException::withMessages(['locationId' => __('dcim::racks.errors.location')]);
        }

        $subnet ??= new Subnet;
        $subnet->forceFill([...$data, 'network' => $network->cidr(), 'gateway' => $gateway])->save();

        return $subnet;
    }

    public function deleteSubnet(Subnet $subnet): void
    {
        $orphans = $subnet->addresses()->get()->filter(fn (IpAddress $address): bool => Subnet::holding($address->host(), $subnet->id) === null);

        if ($orphans->isNotEmpty() || Subnet::query()->whereRaw('network << ?::cidr', [$subnet->network])->exists()) {
            throw ValidationException::withMessages(['subnet' => __('dcim::ipam.errors.subnet_in_use')]);
        }

        $subnet->delete();
    }

    /**
     * @param  array{address: string, status: AddressStatus, dns_name: ?string, mount_id: ?string, configuration_item_id: ?string, description: ?string}  $data
     */
    public function saveAddress(?IpAddress $address, array $data): IpAddress
    {
        $host = IpNetwork::address($data['address']);

        if ($host === null) {
            throw ValidationException::withMessages(['address' => __('dcim::ipam.errors.address')]);
        }

        $subnet = Subnet::holding($host);

        if ($subnet === null) {
            throw ValidationException::withMessages(['address' => __('dcim::ipam.errors.no_subnet')]);
        }

        $network = $subnet->parsed();

        if ($network->family === 4 && $network->prefix < 31 && $this->isEdge($host, $subnet->network)) {
            throw ValidationException::withMessages(['address' => __('dcim::ipam.errors.edge', ['network' => $network->cidr()])]);
        }

        $existing = IpAddress::query()->whereRaw('address = ?::inet', [$host])->when($address !== null, fn ($query) => $query->whereKeyNot($address?->id))->first();

        if ($existing !== null) {
            throw ValidationException::withMessages(['address' => __('dcim::ipam.errors.address_taken', ['address' => $host])]);
        }

        if ($data['mount_id'] !== null && ! Mount::query()->whereKey($data['mount_id'])->exists()) {
            throw ValidationException::withMessages(['holder' => __('dcim::ipam.errors.holder')]);
        }

        if ($data['configuration_item_id'] !== null && $this->items->find([$data['configuration_item_id']]) === []) {
            throw ValidationException::withMessages(['holder' => __('dcim::ipam.errors.holder')]);
        }

        $address ??= new IpAddress;
        $address->forceFill([...$data, 'address' => $host])->save();

        return $address;
    }

    public function deleteAddress(IpAddress $address): void
    {
        $address->delete();
    }

    private function inside(string $address, string $network): bool
    {
        $row = Subnet::query()->getConnection()->selectOne('SELECT ?::inet << ?::cidr AS inside', [$address, $network]);

        return is_object($row) && ($row->inside ?? false) === true;
    }

    private function isEdge(string $address, string $network): bool
    {
        $row = Subnet::query()->getConnection()->selectOne('SELECT ?::inet = host(network(?::cidr))::inet OR ?::inet = host(broadcast(?::cidr))::inet AS edge', [$address, $network, $address, $network]);

        return is_object($row) && ($row->edge ?? false) === true;
    }
}
