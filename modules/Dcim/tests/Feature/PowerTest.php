<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Livewire\Livewire;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Risks\RiskSignal;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Dcim\Application\PowerGrid;
use Modules\Dcim\Application\Risks\PowerRiskSignals;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\PowerConnection;
use Modules\Dcim\Domain\Models\PowerSource;
use Modules\Dcim\Domain\Models\Rack;
use Modules\Dcim\Http\Livewire\PowerIndex;
use Modules\Dcim\Http\Livewire\PowerSourceForm;
use Modules\Dcim\Http\Livewire\ShowEquipment;
use Modules\Dcim\Http\Livewire\ShowPowerSource;
use Modules\Dcim\Tests\Support\DcimScenario;
use Modules\Itam\Domain\Models\Asset;

beforeEach(function (): void {
    app(SyncPermissions::class)->handle();
    $this->organization = Organization::factory()->create();
    app(OrganizationContext::class)->set($this->organization);
    $this->manager = DcimScenario::member($this->organization, DefaultRole::AssetManager);
    $this->room = Location::factory()->create(['kind' => 'room', 'name' => 'Server room']);
    $this->rack = Rack::factory()->create(['location_id' => $this->room->id]);

    $source = fn (string $name, string $kind, ?string $side, ?PowerSource $upstream, ?int $capacity): PowerSource => PowerSource::factory()->create([
        'location_id' => $this->room->id, 'name' => $name, 'kind' => $kind, 'side' => $side, 'upstream_id' => $upstream?->id, 'capacity_watts' => $capacity,
    ]);
    $feedA = $source('Feed A', 'feed', 'a', null, null);
    $feedB = $source('Feed B', 'feed', 'b', null, null);
    $this->upsA = $source('UPS A', 'ups', 'a', $feedA, 10000);
    $this->upsB = $source('UPS B', 'ups', 'b', $feedB, 10000);
    $this->pduA = $source('PDU A1', 'pdu', 'a', $this->upsA, 3680);
    $this->pduA2 = $source('PDU A2', 'pdu', 'a', $this->upsA, 3680);
    $this->pduB = $source('PDU B1', 'pdu', 'b', $this->upsB, 3680);

    $device = fn (string $name, int $position, ?int $watts): Mount => Mount::factory()->create([
        'rack_id' => $this->rack->id, 'asset_id' => Asset::factory()->create(['name' => $name])->id, 'position_u' => $position, 'power_watts' => $watts,
    ]);
    $this->db = $device('db-01', 10, 400);
    $this->web = $device('web-01', 12, 300);
    $this->switch = $device('Switch 1', 40, null);
    $this->app2 = $device('app-02', 14, 200);

    foreach ([[$this->db, $this->pduA], [$this->db, $this->pduB], [$this->web, $this->pduA], [$this->switch, $this->pduA], [$this->app2, $this->pduA], [$this->app2, $this->pduA2]] as [$mount, $pdu]) {
        PowerConnection::factory()->create(['mount_id' => $mount->id, 'source_id' => $pdu->id]);
    }
});

it('adds up what each source carries, in normal operation and if the other side fails', function (): void {
    $grid = PowerGrid::read();

    // db-01 draws half from each side; app-02 is split between two PDUs of the same UPS.
    expect($grid->loadOf($this->pduA))->toBe(['normal' => 200.0 + 300.0 + 100.0, 'worst' => 400.0 + 300.0 + 200.0, 'devices' => 4, 'unknown' => 1])
        ->and($grid->loadOf($this->upsA))->toBe(['normal' => 200.0 + 300.0 + 200.0, 'worst' => 900.0, 'devices' => 4, 'unknown' => 1])
        ->and($grid->loadOf($this->pduB))->toBe(['normal' => 200.0, 'worst' => 400.0, 'devices' => 1, 'unknown' => 0])
        ->and(PowerGrid::percent(700.0, 10000))->toBe(7);
});

it('tells which equipment has two independent supplies and what a failure takes down', function (): void {
    $grid = PowerGrid::read();

    expect($grid->isRedundant($this->db->id))->toBeTrue()
        ->and($grid->isRedundant($this->web->id))->toBeFalse()
        // Two PDUs behind the same UPS are not independent.
        ->and($grid->isRedundant($this->app2->id))->toBeFalse()
        ->and(collect($grid->singleFed())->pluck('id')->sort()->values()->all())->toBe(collect([$this->web->id, $this->switch->id, $this->app2->id])->sort()->values()->all());

    $failure = $grid->failureOf($this->upsA);
    expect(collect($failure['lost'])->pluck('id')->sort()->values()->all())->toBe(collect([$this->web->id, $this->switch->id, $this->app2->id])->sort()->values()->all())
        ->and(collect($failure['degraded'])->pluck('id')->all())->toBe([$this->db->id]);

    $failure = $grid->failureOf($this->pduA2);
    expect($failure['lost'])->toBe([])->and(collect($failure['degraded'])->pluck('id')->all())->toBe([$this->app2->id]);
});

it('shows a failure\'s impact on the CMDB', function (): void {
    $item = ConfigurationItem::factory()->create(['name' => 'web-01 server']);
    $service = ConfigurationItem::factory()->create(['name' => 'Online shop']);
    app(ConfigurationItems::class)->link(Asset::query()->findOrFail($this->web->asset_id), $item->id, $this->manager);
    Modules\Cmdb\Domain\Models\Relationship::query()->forceCreate(['source_id' => $service->id, 'target_id' => $item->id, 'type' => 'depends_on']);

    $this->actingAs($this->manager);
    Livewire::test(ShowPowerSource::class, ['source' => $this->upsA])
        ->assertSee('web-01')->assertSee('db-01')
        ->assertSee('Online shop');
});

it('keeps the chain free of loops, and deletes only sources nothing depends on', function (): void {
    $this->actingAs($this->manager);
    Livewire::test(PowerSourceForm::class)
        ->set('kind', 'ups')->set('name', 'UPS C')->set('locationId', $this->room->id)->set('upstreamId', $this->upsA->id)->set('capacityWatts', '6000')
        ->call('save')->assertHasNoErrors();
    $upsC = PowerSource::query()->where('name', 'UPS C')->sole();

    Livewire::test(PowerSourceForm::class, ['source' => $this->upsA])->set('upstreamId', $upsC->id)->call('save')->assertHasErrors('upstreamId');
    Livewire::test(PowerSourceForm::class)->set('kind', 'pdu')->set('name', 'pdu a1')->set('locationId', $this->room->id)->call('save')->assertHasErrors('name');

    Livewire::test(ShowPowerSource::class, ['source' => $this->upsA])->call('delete')->assertHasErrors('source');
    Livewire::test(ShowPowerSource::class, ['source' => $upsC])->call('delete')->assertRedirect(route('dcim.power.index'));
    expect(PowerSource::query()->whereKey($upsC->id)->exists())->toBeFalse();
});

it('plugs equipment in and out from its page, one outlet each', function (): void {
    $this->actingAs($this->manager);
    $page = Livewire::test(ShowEquipment::class, ['mount' => $this->web])->assertSee(__('dcim::power.single_fed'));

    $page->set('powerSourceId', $this->pduB->id)->set('outlet', '7')->call('plugIn')->assertHasNoErrors()
        ->assertSee(__('dcim::power.redundant'))
        ->set('powerSourceId', $this->pduB->id)->call('plugIn')->assertHasErrors('powerSourceId');
    Livewire::test(ShowEquipment::class, ['mount' => $this->switch])->set('powerSourceId', $this->pduB->id)->set('outlet', '7')->call('plugIn')->assertHasErrors('outlet');

    $connection = PowerConnection::query()->where('mount_id', $this->web->id)->where('source_id', $this->pduB->id)->sole();
    $page->call('unplug', $connection->id);
    expect(PowerConnection::query()->whereKey($connection->id)->exists())->toBeFalse();
});

it('suggests risks for single-fed equipment the CMDB relies on, and for overload on failure', function (): void {
    $item = ConfigurationItem::factory()->create();
    app(ConfigurationItems::class)->link(Asset::query()->findOrFail($this->web->asset_id), $item->id, $this->manager);
    $this->pduB->forceFill(['capacity_watts' => 300])->save();

    $keys = array_map(fn (RiskSignal $signal): string => $signal->key, app(PowerRiskSignals::class)());

    expect($keys)->toContain('dcim.single_fed:'.$this->web->id, 'dcim.overload:'.$this->pduB->id)
        // Single-fed equipment nobody relies on in the CMDB is left out.
        ->not->toContain('dcim.single_fed:'.$this->switch->id);
});

it('lets IT staff see the power chain and only managers change it', function (): void {
    $agent = DcimScenario::member($this->organization, DefaultRole::Agent);

    $this->actingAs($agent);
    Livewire::test(PowerIndex::class)->assertSee('UPS A')->assertSee('PDU A1')->assertDontSee(__('dcim::power.new'));
    $this->get(route('dcim.power.create'))->assertForbidden();
    Livewire::test(ShowPowerSource::class, ['source' => $this->upsA])->call('delete')->assertForbidden();
    Livewire::test(ShowEquipment::class, ['mount' => $this->web])->set('powerSourceId', $this->pduB->id)->call('plugIn')->assertForbidden();

    $this->actingAs(DcimScenario::member($this->organization, DefaultRole::Requester))->get(route('dcim.power.index'))->assertForbidden();

    $other = Organization::factory()->create();
    $foreign = app(OrganizationContext::class)->runAs($other, fn () => PowerSource::factory()->create());
    $this->actingAs($this->manager)->get(route('dcim.power.show', $foreign->id))->assertNotFound();
    Livewire::test(ShowEquipment::class, ['mount' => $this->web])->set('powerSourceId', $foreign->id)->call('plugIn')->assertNotFound();
});

it('warns when a source would be near its capacity if the other side failed', function (): void {
    // Normally 200 W of 450 W (44%); 400 W (89%) if side A failed.
    $this->pduB->forceFill(['capacity_watts' => 450])->save();

    $this->actingAs($this->manager);
    Livewire::test(ShowPowerSource::class, ['source' => $this->pduB])->assertSee(__('dcim::power.high'));
});
