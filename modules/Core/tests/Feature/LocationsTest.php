<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Http\Filament\Resources\Locations\Pages\ManageLocations;
use Modules\Core\Tests\Support\ContractScenario;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->scenario = ContractScenario::create();
    $this->actingAs($this->scenario->manager);
});

it('lets asset managers and team leads into the panel, where they see only what they may manage', function (): void {
    $this->get('/admin/locations')->assertOk();
    $this->get('/admin/people')->assertForbidden();
    $this->get('/admin/ticketing/mailboxes')->assertForbidden();
    $this->get('/admin/directory-group-mappings')->assertForbidden();

    $this->actingAs($this->scenario->member(DefaultRole::TeamLead))->get('/admin/locations')->assertOk();
    $this->actingAs($this->scenario->agent)->get('/admin')->assertForbidden();
});

it('nests locations, unique by name within the same parent', function (): void {
    Livewire::test(ManageLocations::class)
        ->callAction('create', data: ['name' => 'Bucharest HQ', 'kind' => 'site', 'is_active' => true])
        ->assertHasNoFormErrors();
    $site = Location::query()->where('name', 'Bucharest HQ')->sole();

    Livewire::test(ManageLocations::class)
        ->callAction('create', data: ['name' => 'Server room', 'kind' => 'room', 'parent_id' => $site->id, 'is_active' => true])
        ->assertHasNoFormErrors();
    Livewire::test(ManageLocations::class)
        ->callAction('create', data: ['name' => 'Server room', 'kind' => 'room', 'is_active' => true])
        ->assertHasNoFormErrors();
    Livewire::test(ManageLocations::class)
        ->callAction('create', data: ['name' => 'server ROOM', 'kind' => 'room', 'parent_id' => $site->id, 'is_active' => true])
        ->assertHasFormErrors(['name']);

    expect(Location::paths())->toContain('Bucharest HQ › Server room');
});

it('cannot put a location inside itself or one of its sub-locations', function (): void {
    $site = Location::factory()->create(['name' => 'Site']);
    $building = Location::factory()->create(['name' => 'Building', 'kind' => 'building', 'parent_id' => $site->id]);

    Livewire::test(ManageLocations::class)
        ->callTableAction('edit', $site, data: ['parent_id' => $building->id])
        ->assertHasTableActionErrors(['parent_id']);

    expect($site->fresh()->parent_id)->toBeNull();
});

it('is refused to agents', function (): void {
    $this->actingAs($this->scenario->agent);

    expect($this->scenario->agent->can('create', Location::class))->toBeFalse();
});
