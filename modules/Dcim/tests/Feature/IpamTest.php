<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Livewire\Livewire;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Dcim\Domain\Models\IpAddress;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\Rack;
use Modules\Dcim\Domain\Models\Subnet;
use Modules\Dcim\Domain\Models\Vlan;
use Modules\Dcim\Http\Livewire\IpamIndex;
use Modules\Dcim\Http\Livewire\ShowEquipment;
use Modules\Dcim\Http\Livewire\ShowSubnet;
use Modules\Dcim\Http\Livewire\SubnetForm;
use Modules\Dcim\Http\Livewire\VlanIndex;
use Modules\Dcim\Tests\Support\DcimScenario;
use Modules\Itam\Domain\Models\Asset;

beforeEach(function (): void {
    app(SyncPermissions::class)->handle();
    $this->organization = Organization::factory()->create();
    app(OrganizationContext::class)->set($this->organization);
    $this->manager = DcimScenario::member($this->organization, DefaultRole::AssetManager);
    $this->actingAs($this->manager);
});

function subnet(string $network, ?string $gateway = null, string $purpose = ''): Subnet
{
    Livewire::test(SubnetForm::class)->set('network', $network)->set('gateway', $gateway ?? '')->set('purpose', $purpose)->call('save')->assertHasNoErrors();

    return Subnet::query()->whereRaw('network = ?::cidr', [$network])->sole();
}

function giveAddress(Subnet $subnet, string $address, string $holder = ''): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::test(ShowSubnet::class, ['subnet' => $subnet])->set('address', $address)->set('holder', $holder)->call('save');
}

it('records subnets, suggesting the network when an address was typed, and nests them', function (): void {
    Livewire::test(SubnetForm::class)->set('network', '10.20.0.7/16')->call('save')
        ->assertHasErrors('network')->assertSee('10.20.0.0/16');
    $parent = subnet('10.20.0.0/16', null, 'Head office');
    subnet('10.20.1.0/24', '10.20.1.1', 'Servers');
    Livewire::test(SubnetForm::class)->set('network', '10.20.1.0/24')->call('save')->assertHasErrors('network');
    Livewire::test(SubnetForm::class)->set('network', '10.20.2.0/24')->set('gateway', '10.20.3.1')->call('save')->assertHasErrors('gateway');
    subnet('2001:db8:10::/64', '2001:db8:10::1', 'IPv6 servers');

    Livewire::test(IpamIndex::class)->assertSeeInOrder(['10.20.0.0/16', '10.20.1.0/24'])->assertSee('2001:db8:10::/64');
    Livewire::test(IpamIndex::class)->set('q', '10.20.1.50')->assertSee('10.20.1.0/24')->assertSee('10.20.0.0/16')->assertDontSee('2001:db8:10::/64');
    Livewire::test(ShowSubnet::class, ['subnet' => $parent])->assertSee('10.20.1.0/24');
});

it('gives out addresses only inside a subnet, once each, skipping the network, broadcast and gateway', function (): void {
    $servers = subnet('10.20.1.0/29', '10.20.1.1');

    giveAddress($servers, '10.30.0.5')->assertHasErrors('address');
    giveAddress($servers, '10.20.1.0')->assertHasErrors('address');
    giveAddress($servers, '10.20.1.7')->assertHasErrors('address');
    giveAddress($servers, '10.20.1.2')->assertHasNoErrors();
    giveAddress($servers, '10.20.1.2')->assertHasErrors('address');

    Livewire::test(ShowSubnet::class, ['subnet' => $servers])->call('useNextFree')->assertSet('address', '10.20.1.3');
    expect($servers->nextFree())->toBe('10.20.1.3');

    foreach (['10.20.1.3', '10.20.1.4', '10.20.1.5', '10.20.1.6'] as $address) {
        giveAddress($servers, $address)->assertHasNoErrors();
    }
    Livewire::test(ShowSubnet::class, ['subnet' => $servers])->call('useNextFree')->assertHasErrors('address')
        ->assertSee(__('dcim::ipam.usage', ['used' => 5, 'usable' => 6, 'percent' => 83]));

    $v6 = subnet('2001:db8:10::/64', '2001:db8:10::1');
    expect($v6->nextFree())->toBe('2001:db8:10::');
    giveAddress($v6, '2001:DB8:10::0010')->assertHasNoErrors();
    expect(IpAddress::query()->whereRaw('address = ?::inet', ['2001:db8:10::10'])->exists())->toBeTrue();
});

it('refuses duplicates and ranges in the database too', function (): void {
    IpAddress::factory()->create(['address' => '192.168.1.10']);

    expect(fn () => IpAddress::factory()->create(['address' => '192.168.1.10']))->toThrow(QueryException::class)
        ->and(fn () => IpAddress::factory()->create(['address' => '192.168.1.0/24']))->toThrow(QueryException::class)
        ->and(fn () => Subnet::factory()->create(['network' => '10.9.0.0/24', 'gateway' => '10.8.0.1']))->toThrow(QueryException::class);
});

it('assigns addresses to equipment or CMDB items, and shows them on the equipment', function (): void {
    $servers = subnet('10.20.1.0/24');
    $rack = Rack::factory()->create(['location_id' => Location::factory()->create(['kind' => 'room'])->id]);
    $mount = Mount::factory()->create(['rack_id' => $rack->id, 'asset_id' => Asset::factory()->create(['name' => 'db-01'])->id]);
    $item = ConfigurationItem::factory()->create(['name' => 'Load balancer VIP']);

    giveAddress($servers, '10.20.1.10', 'mount:'.$mount->id)->assertHasNoErrors();
    giveAddress($servers, '10.20.1.11', 'item:'.$item->id)->assertHasNoErrors();

    Livewire::test(ShowSubnet::class, ['subnet' => $servers])->assertSee('db-01')->assertSee('Load balancer VIP');
    Livewire::test(ShowEquipment::class, ['mount' => $mount])->assertSee('10.20.1.10')->assertSee('10.20.1.0/24');
});

it('deletes a subnet only when nothing depends on it', function (): void {
    $parent = subnet('10.20.0.0/16');
    $child = subnet('10.20.1.0/24');
    giveAddress($child, '10.20.1.10')->assertHasNoErrors();

    Livewire::test(ShowSubnet::class, ['subnet' => $parent])->call('deleteSubnet')->assertHasErrors('subnet');
    // The address is still held by the larger subnet once the smaller one goes.
    Livewire::test(ShowSubnet::class, ['subnet' => $child])->call('deleteSubnet')->assertRedirect(route('dcim.ipam.index'));
    Livewire::test(ShowSubnet::class, ['subnet' => $parent])->call('deleteSubnet')->assertHasErrors('subnet');
});

it('keeps VLAN IDs unique per site, and VLANs with subnets', function (): void {
    $site = Location::factory()->create(['name' => 'HQ']);
    $page = Livewire::test(VlanIndex::class)
        ->set('vid', '10')->set('name', 'Servers')->call('save')->assertHasNoErrors()
        ->set('vid', '10')->set('name', 'Again')->call('save')->assertHasErrors('vid')
        ->set('vid', '10')->set('name', 'HQ servers')->set('vlanLocationId', $site->id)->call('save')->assertHasNoErrors();

    $vlan = Vlan::query()->whereNull('location_id')->sole();
    Livewire::test(SubnetForm::class)->set('network', '10.30.0.0/24')->set('vlanId', $vlan->id)->call('save')->assertHasNoErrors();
    $page->call('delete', $vlan->id)->assertHasErrors('vlans');
    expect(Vlan::query()->count())->toBe(2);
});

it('lets IT staff look up addresses, managers change them, and keeps organizations apart', function (): void {
    $servers = subnet('10.20.1.0/24');
    $agent = DcimScenario::member($this->organization, DefaultRole::Agent);

    $this->actingAs($agent);
    Livewire::test(ShowSubnet::class, ['subnet' => $servers])->assertDontSee(__('dcim::ipam.add_address'))
        ->set('address', '10.20.1.5')->call('save')->assertForbidden();
    $this->get(route('dcim.ipam.subnets.create'))->assertForbidden();
    $this->actingAs(DcimScenario::member($this->organization, DefaultRole::Requester))->get(route('dcim.ipam.index'))->assertForbidden();

    $other = Organization::factory()->create();
    [$foreignSubnet, $foreignMount] = app(OrganizationContext::class)->runAs($other, function (): array {
        $rack = Rack::factory()->create(['location_id' => Location::factory()->create(['kind' => 'room'])->id]);

        return [Subnet::factory()->create(['network' => '10.20.1.0/24']), Mount::factory()->create(['rack_id' => $rack->id])];
    });

    $this->actingAs($this->manager);
    $this->get(route('dcim.ipam.subnets.show', $foreignSubnet->id))->assertNotFound();
    giveAddress($servers, '10.20.1.5', 'mount:'.$foreignMount->id)->assertHasErrors('holder');
    // The same address in another organization is no duplicate.
    giveAddress($servers, '10.20.1.6')->assertHasNoErrors();
    app(OrganizationContext::class)->runAs($other, fn () => IpAddress::factory()->create(['address' => '10.20.1.6']));
    expect(IpAddress::query()->withoutGlobalScopes()->whereRaw('address = ?::inet', ['10.20.1.6'])->count())->toBe(2);
});
