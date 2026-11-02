<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Livewire\Livewire;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Dcim\Application\Actions\AddPorts;
use Modules\Dcim\Application\CablePath;
use Modules\Dcim\Domain\Enums\MountFace;
use Modules\Dcim\Domain\Enums\PortKind;
use Modules\Dcim\Domain\Models\Cable;
use Modules\Dcim\Domain\Models\CableEnd;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\Port;
use Modules\Dcim\Domain\Models\Rack;
use Modules\Dcim\Http\Livewire\CableIndex;
use Modules\Dcim\Http\Livewire\ShowEquipment;
use Modules\Dcim\Http\Livewire\ShowRack;
use Modules\Dcim\Tests\Support\DcimScenario;
use Modules\Itam\Domain\Models\Asset;

beforeEach(function (): void {
    app(SyncPermissions::class)->handle();
    $this->organization = Organization::factory()->create();
    app(OrganizationContext::class)->set($this->organization);
    $this->manager = DcimScenario::member($this->organization, DefaultRole::AssetManager);
    $this->rack = Rack::factory()->create(['location_id' => Location::factory()->create(['kind' => 'room'])->id, 'name' => 'R01']);
});

function equipment(Rack $rack, string $name, int $position): Mount
{
    return Mount::factory()->create(['rack_id' => $rack->id, 'asset_id' => Asset::factory()->create(['name' => $name])->id, 'position_u' => $position]);
}

function cable(Port $from, Port $to, string $label): void
{
    Livewire::test(ShowEquipment::class, ['mount' => $from->mount])
        ->call('startConnecting', $from->id)
        ->call('chooseEquipment', $to->mount_id)
        ->set('otherPortId', $to->id)->set('cableLabel', $label)->set('cableType', 'cat6a')
        ->call('saveCable')->assertHasNoErrors();
}

it('expands a series of port names', function (): void {
    expect(AddPorts::names('Gi1/0/[1-3]'))->toBe(['Gi1/0/1', 'Gi1/0/2', 'Gi1/0/3'])
        ->and(AddPorts::names('[01-03]'))->toBe(['01', '02', '03'])
        ->and(AddPorts::names(' eth0 '))->toBe(['eth0'])
        ->and(AddPorts::names('p[5-1]'))->toBe([])
        ->and(AddPorts::names('p[1-300]'))->toBe([]);
});

it('adds ports one by one or in series, and wires a patch panel\'s front to its rear', function (): void {
    $this->actingAs($this->manager);
    $switch = equipment($this->rack, 'Switch 1', 40);
    $panel = equipment($this->rack, 'Panel A', 39);

    Livewire::test(ShowEquipment::class, ['mount' => $switch])
        ->set('pattern', 'Gi1/0/[1-24]')->set('kind', 'rj45')->call('addPorts')->assertHasNoErrors()
        ->set('pattern', 'gi1/0/[24-25]')->call('addPorts')->assertHasErrors('pattern');
    Livewire::test(ShowEquipment::class, ['mount' => $panel])
        ->set('pattern', '[01-12]')->set('patchPanel', true)->call('addPorts')->assertHasNoErrors();

    expect($switch->ports()->count())->toBe(24)
        ->and($panel->ports()->count())->toBe(24);
    $front = $panel->ports()->where('name', '01')->sole();
    expect($front->side)->toBe(MountFace::Front)
        ->and($front->rearPort?->name)->toBe('01 rear')
        ->and($front->rearPort?->frontPort?->id)->toBe($front->id);
});

it('lays one cable per port and traces a path through two patch panels', function (): void {
    $this->actingAs($this->manager);
    $server = equipment($this->rack, 'db-01', 10);
    $panelA = equipment($this->rack, 'Panel A', 20);
    $other = Rack::factory()->create(['location_id' => $this->rack->location_id, 'name' => 'R02']);
    $panelB = equipment($other, 'Panel B', 20);
    $switch = equipment($other, 'Core switch', 40);
    $add = app(AddPorts::class);
    $add->handle($server, 'eth[0-1]', PortKind::Rj45, MountFace::Rear, false);
    $add->handle($panelA, '[1-2]', PortKind::Rj45, MountFace::Front, true);
    $add->handle($panelB, '[1-2]', PortKind::Rj45, MountFace::Front, true);
    $add->handle($switch, 'Gi[1-2]', PortKind::Rj45, MountFace::Front, false);
    $port = fn (Mount $mount, string $name): Port => $mount->ports()->where('name', $name)->sole();

    cable($port($server, 'eth0'), $port($panelA, '1'), 'C-001');
    cable($port($panelA, '1 rear'), $port($panelB, '1 rear'), 'TRUNK-1');
    cable($port($panelB, '1'), $port($switch, 'Gi1'), 'C-002');

    // A port takes one cable; the same label twice is refused.
    Livewire::test(ShowEquipment::class, ['mount' => $server])
        ->call('startConnecting', $port($server, 'eth1')->id)->call('chooseEquipment', $switch->id)
        ->set('otherPortId', $port($switch, 'Gi1')->id)->call('saveCable')->assertHasErrors('otherPortId')
        ->set('otherPortId', $port($switch, 'Gi2')->id)->set('cableLabel', 'c-001')->call('saveCable')->assertHasErrors('cableLabel');

    $path = CablePath::from($port($server, 'eth0'));
    expect(array_map(fn (array $step): string => $step['port']->name, $path))->toBe(['eth0', '1', '1 rear', '1 rear', '1', 'Gi1'])
        ->and(array_map(fn (array $step): ?string => $step['via']?->label, $path))->toBe([null, 'C-001', null, 'TRUNK-1', null, 'C-002'])
        ->and($path[2]['throughPanel'])->toBeTrue();

    Livewire::test(ShowEquipment::class, ['mount' => $server])->call('trace', $port($server, 'eth0')->id)
        ->assertSeeInOrder(['eth0', 'C-001', 'Panel A', __('dcim::cabling.through_panel'), 'TRUNK-1', 'Panel B', 'C-002', 'Core switch']);

    // The database refuses a second cable on a port, whatever the application does.
    $extra = Cable::factory()->create();
    expect(fn () => (new CableEnd)->forceFill(['cable_id' => $extra->id, 'end' => 'a', 'port_id' => $port($switch, 'Gi1')->id])->save())->toThrow(QueryException::class);
});

it('edits and removes cables, and keeps cabled ports', function (): void {
    $this->actingAs($this->manager);
    $server = equipment($this->rack, 'db-01', 10);
    $switch = equipment($this->rack, 'Switch 1', 40);
    app(AddPorts::class)->handle($server, 'eth0', PortKind::Rj45, MountFace::Rear, false);
    app(AddPorts::class)->handle($switch, 'Gi1', PortKind::Rj45, MountFace::Front, false);
    $eth0 = $server->ports()->sole();
    cable($eth0, $switch->ports()->sole(), 'C-100');

    Livewire::test(ShowEquipment::class, ['mount' => $server])
        ->call('deletePort', $eth0->id)->assertHasErrors('ports')
        ->call('editCable', $eth0->id)->set('cableColour', 'blue')->set('cableLength', '2.5')->call('saveCable')->assertHasNoErrors();
    expect(Cable::query()->sole())->colour->toBe('blue')->length_m->toBe('2.50');

    Livewire::test(CableIndex::class)->set('q', 'c-1')->assertSee('C-100')->assertSee('db-01');

    Livewire::test(ShowEquipment::class, ['mount' => $server])->call('disconnect', $eth0->id)->call('deletePort', $eth0->id)->assertHasNoErrors();
    expect(Cable::query()->exists())->toBeFalse()->and($server->ports()->exists())->toBeFalse();

    // Taking equipment out of the rack removes its ports and cables.
    app(AddPorts::class)->handle($server, 'eth0', PortKind::Rj45, MountFace::Rear, false);
    cable($server->ports()->sole(), $switch->ports()->sole(), 'C-101');
    Livewire::test(ShowRack::class, ['rack' => $this->rack])->call('unmount', $server->id);
    expect(Port::query()->where('mount_id', $server->id)->exists())->toBeFalse()->and(Cable::query()->count())->toBe(1)
        ->and(CableEnd::query()->count())->toBe(1);
});

it('lets IT staff look but only managers change the cabling', function (): void {
    $server = equipment($this->rack, 'db-01', 10);
    app(AddPorts::class)->handle($server, 'eth0', PortKind::Rj45, MountFace::Rear, false);
    $agent = DcimScenario::member($this->organization, DefaultRole::Agent);

    $this->actingAs($agent);
    Livewire::test(ShowEquipment::class, ['mount' => $server])->assertSee('eth0')->assertDontSee(__('dcim::cabling.add_ports'))
        ->call('startConnecting', $server->ports()->sole()->id)->assertForbidden();
    $this->get(route('dcim.cables.index'))->assertOk();

    $this->actingAs(DcimScenario::member($this->organization, DefaultRole::Requester))->get(route('dcim.equipment.show', $server))->assertForbidden();
});

it('never cables to another organization\'s equipment', function (): void {
    $this->actingAs($this->manager);
    $server = equipment($this->rack, 'db-01', 10);
    app(AddPorts::class)->handle($server, 'eth0', PortKind::Rj45, MountFace::Rear, false);

    $other = Organization::factory()->create();
    [$foreignMount, $foreignPort] = app(OrganizationContext::class)->runAs($other, function (): array {
        $rack = Rack::factory()->create(['location_id' => Location::factory()->create(['kind' => 'room'])->id]);
        $mount = Mount::factory()->create(['rack_id' => $rack->id, 'asset_id' => Asset::factory()->create()->id]);

        return [$mount, Port::factory()->create(['mount_id' => $mount->id])];
    });

    $page = fn () => Livewire::test(ShowEquipment::class, ['mount' => $server])->call('startConnecting', $server->ports()->sole()->id);
    $page()->call('chooseEquipment', $foreignMount->id)->assertNotFound();
    $page()->set('otherPortId', $foreignPort->id)->call('saveCable')->assertNotFound();
    $this->get(route('dcim.equipment.show', $foreignMount->id))->assertNotFound();
    expect(Cable::query()->exists())->toBeFalse();
});
