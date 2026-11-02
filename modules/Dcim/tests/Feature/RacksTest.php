<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Dcim\Application\Elevation;
use Modules\Dcim\Domain\Enums\MountFace;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\Rack;
use Modules\Dcim\Http\Livewire\RackForm;
use Modules\Dcim\Http\Livewire\RackIndex;
use Modules\Dcim\Http\Livewire\ShowRack;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Http\Livewire\ShowAsset;

beforeEach(function (): void {
    app(SyncPermissions::class)->handle();
    $this->organization = Organization::factory()->create();
    app(OrganizationContext::class)->set($this->organization);
    $this->manager = dcimMember($this->organization, DefaultRole::AssetManager);
    $this->agent = dcimMember($this->organization, DefaultRole::Agent);
    $this->room = Location::factory()->create(['name' => 'Server room', 'kind' => 'room']);
});

function dcimMember(Organization $organization, DefaultRole $role): User
{
    $factory = User::factory()->memberOf($organization);
    $user = ($role === DefaultRole::Requester ? $factory : $factory->withTwoFactor())->create();
    app(OrganizationContext::class)->runAs($organization, fn () => $user->assignRole($role->value));

    return User::query()->findOrFail($user->id);
}

function mountForm(Rack $rack, Asset $asset, int|string $position, int $height = 1, string $face = 'front', bool $fullDepth = true): Testable
{
    return Livewire::test(ShowRack::class, ['rack' => $rack])
        ->call('chooseAsset', $asset->id)
        ->set('positionU', (string) $position)->set('heightU', (string) $height)
        ->set('face', $face)->set('fullDepth', $fullDepth)
        ->call('save');
}

it('lets asset managers add racks to rooms, and shows them by room to IT staff', function (): void {
    $this->actingAs($this->manager);
    Livewire::test(RackForm::class)
        ->set('locationId', $this->room->id)->set('name', 'R01')->set('row', 'A')->set('position', '1')->set('heightU', '42')->set('maxPowerWatts', '5000')
        ->call('save')->assertHasNoErrors();
    Livewire::test(RackForm::class)
        ->set('locationId', $this->room->id)->set('name', 'r01')->set('heightU', '42')
        ->call('save')->assertHasErrors('name');

    $rack = Rack::query()->sole();
    expect($rack->max_power_watts)->toBe(5000)
        ->and(AuditLog::query()->where('auditable_id', $rack->id)->where('event', 'created')->exists())->toBeTrue();

    $this->actingAs($this->agent);
    Livewire::test(RackIndex::class)->assertSee('Server room')->assertSee('R01')->assertDontSee(__('dcim::racks.new'));
    $this->get(route('dcim.racks.create'))->assertForbidden();
    Livewire::test(ShowRack::class, ['rack' => $rack])->assertDontSee(__('dcim::racks.mount_heading'));

    $requester = dcimMember($this->organization, DefaultRole::Requester);
    $this->actingAs($requester)->get(route('dcim.racks.index'))->assertForbidden();
});

it('mounts assets on free units only, sharing a unit front and rear for half-depth equipment', function (): void {
    $this->actingAs($this->manager);
    $rack = Rack::factory()->create(['location_id' => $this->room->id, 'height_u' => 42]);
    $server = Asset::factory()->create(['name' => 'db-01']);
    $panel = Asset::factory()->create(['name' => 'Patch panel 1']);
    $switch = Asset::factory()->create(['name' => 'Switch 1']);
    $other = Asset::factory()->create(['name' => 'web-02']);

    mountForm($rack, $server, 10, 2)->assertHasNoErrors();
    // Units 10-11 are taken on both sides by the full-depth server.
    mountForm($rack, $other, 11, 1, 'rear')->assertHasErrors('positionU')->assertSee('db-01');
    // A half-depth panel at the front and a half-depth switch behind it share U20.
    mountForm($rack, $panel, 20, 1, 'front', false)->assertHasNoErrors();
    mountForm($rack, $switch, 20, 1, 'rear', false)->assertHasNoErrors();
    // Beyond the top of the rack.
    mountForm($rack, $other, 42, 2)->assertHasErrors('positionU');
    // The same asset is never in two places.
    mountForm($rack, $server, 30)->assertHasErrors('assetId');

    expect(Mount::query()->count())->toBe(3)
        ->and($server->fresh()->location_id)->toBe($this->room->id);

    $front = collect(Elevation::of($rack->fresh()->load('mounts'), MountFace::Front));
    // 42 units, the 2U server drawn as one block.
    expect($front)->toHaveCount(41)
        ->and($front->firstWhere('top', 11)['bottom'])->toBe(10)
        ->and($front->firstWhere('top', 20)['mount']?->asset_id)->toBe($panel->id)
        ->and(collect(Elevation::of($rack->fresh()->load('mounts'), MountFace::Rear))->firstWhere('top', 20)['mount']?->asset_id)->toBe($switch->id);
});

it('moves and takes out equipment, and records it', function (): void {
    $this->actingAs($this->manager);
    $rack = Rack::factory()->create(['location_id' => $this->room->id]);
    $server = Asset::factory()->create(['name' => 'db-01']);
    mountForm($rack, $server, 10, 2)->assertHasNoErrors();
    $mount = Mount::query()->sole();

    $page = Livewire::test(ShowRack::class, ['rack' => $rack])
        ->call('edit', $mount->id)->set('positionU', '11')->call('save')->assertHasNoErrors();
    expect($mount->fresh()->position_u)->toBe(11);

    // Zero-U equipment on the rack's side takes no unit.
    $pdu = Asset::factory()->create(['name' => 'PDU A']);
    mountForm($rack, $pdu, '', 1)->assertHasNoErrors();
    expect(Mount::query()->where('asset_id', $pdu->id)->sole()->position_u)->toBeNull();

    $page->call('unmount', $mount->id);
    expect(Mount::query()->whereKey($mount->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('auditable_id', $mount->id)->pluck('event')->map(fn ($event) => $event instanceof BackedEnum ? $event->value : $event)->all())->toContain('created', 'updated', 'deleted');
});

it('refuses overlapping units even past the application', function (): void {
    $rack = Rack::factory()->create(['location_id' => $this->room->id, 'height_u' => 10]);
    Mount::factory()->create(['rack_id' => $rack->id, 'position_u' => 3, 'height_u' => 2]);

    expect(fn () => Mount::factory()->create(['rack_id' => $rack->id, 'position_u' => 4, 'height_u' => 1, 'face' => 'rear', 'full_depth' => false]))
        ->toThrow(QueryException::class);
    expect(fn () => Mount::factory()->create(['rack_id' => $rack->id, 'position_u' => 10, 'height_u' => 2]))
        ->toThrow(QueryException::class);
    expect(fn () => $rack->forceFill(['height_u' => 3])->save())->toThrow(QueryException::class);
});

it('keeps racks no shorter than their equipment and deletes only empty ones', function (): void {
    $this->actingAs($this->manager);
    $rack = Rack::factory()->create(['location_id' => $this->room->id, 'height_u' => 42]);
    mountForm($rack, Asset::factory()->create(), 30, 2)->assertHasNoErrors();

    Livewire::test(RackForm::class, ['rack' => $rack])->set('heightU', '24')->call('save')->assertHasErrors('heightU');
    Livewire::test(ShowRack::class, ['rack' => $rack])->call('deleteRack')->assertHasErrors('rack');

    Mount::query()->delete();
    Livewire::test(ShowRack::class, ['rack' => $rack])->call('deleteRack')->assertRedirect(route('dcim.racks.index'));
    expect(Rack::query()->exists())->toBeFalse();
});

it('shows on the asset\'s page where it is mounted', function (): void {
    $this->actingAs($this->manager);
    $rack = Rack::factory()->create(['location_id' => $this->room->id, 'name' => 'R07']);
    $server = Asset::factory()->create(['name' => 'db-01']);
    mountForm($rack, $server, 5, 2)->assertHasNoErrors();

    Livewire::test(ShowAsset::class, ['asset' => $server])
        ->assertSee(__('dcim::racks.placement', ['rack' => 'R07', 'units' => 'U5–U6', 'face' => 'Front', 'room' => 'Server room']))
        ->assertSeeHtml(route('dcim.racks.show', $rack));
});

it('never shows or mounts across organizations', function (): void {
    $rack = Rack::factory()->create(['location_id' => $this->room->id]);
    $other = Organization::factory()->create();
    $foreignAsset = app(OrganizationContext::class)->runAs($other, fn () => Asset::factory()->create());
    $foreignRack = app(OrganizationContext::class)->runAs($other, fn () => Rack::factory()->create(['location_id' => Location::factory()->create(['kind' => 'room'])->id]));

    $this->actingAs($this->manager);
    mountForm($rack, $foreignAsset, 1)->assertHasErrors('assetId');
    $this->get(route('dcim.racks.show', $foreignRack->id))->assertNotFound();
    Livewire::test(RackForm::class)->set('locationId', $foreignRack->location_id)->set('name', 'X')->set('heightU', '42')->call('save')->assertHasErrors('locationId');
});
