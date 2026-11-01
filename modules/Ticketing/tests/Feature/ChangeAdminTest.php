<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Application\Changes\SyncCabMembers;
use Modules\Ticketing\Domain\Changes\ChangeRisk;
use Modules\Ticketing\Domain\Models\CabBoard;
use Modules\Ticketing\Domain\Models\ChangeTemplate;
use Modules\Ticketing\Http\Filament\Resources\CabBoards\Pages\ManageCabBoards;
use Modules\Ticketing\Http\Filament\Resources\ChangeTemplates\Pages\ManageChangeTemplates;
use Modules\Ticketing\Tests\Support\TicketingScenario;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->desk = TicketingScenario::create();
    app(OrganizationContext::class)->runAs($this->desk->organization, fn () => $this->desk->lead->givePermissionTo('core.admin.access'));
});

it('gives team leads the change settings and keeps agents out', function (): void {
    foreach (['/admin/ticketing/cab-boards', '/admin/ticketing/change-templates'] as $path) {
        $this->actingAs($this->desk->lead)->get($path)->assertOk();
    }

    app(OrganizationContext::class)->runAs($this->desk->organization, fn () => $this->desk->agent->givePermissionTo('core.admin.access'));
    $this->actingAs($this->desk->agent)->get('/admin/ticketing/cab-boards')->assertForbidden();
});

it('creates an emergency board with members and audits membership', function (): void {
    $this->actingAs($this->desk->lead);
    $member = $this->desk->member(DefaultRole::CabMember);

    Livewire::test(ManageCabBoards::class)
        ->callAction('create', data: ['name' => 'ECAB', 'is_emergency' => true, 'is_active' => true, 'member_ids' => [$member->id, $this->desk->lead->id]])
        ->assertHasNoFormErrors();

    $board = CabBoard::query()->where('name', 'ECAB')->sole();
    expect($board->is_emergency)->toBeTrue()
        ->and($board->members()->count())->toBe(2)
        ->and(AuditLog::query()->where('auditable_id', $board->id)->where('new_values->members', '!=', null)->exists())->toBeTrue();
});

it('refuses board members from another organization', function (): void {
    $board = CabBoard::query()->create(['name' => 'CAB']);
    $outsider = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => User::factory()->create());

    app(SyncCabMembers::class)->handle($board, [$outsider->id]);
})->throws(InvalidArgumentException::class);

it('offers only people of the organization in the form', function (): void {
    $this->actingAs($this->desk->lead);
    $outsider = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => User::factory()->create());

    Livewire::test(ManageCabBoards::class)
        ->callAction('create', data: ['name' => 'CAB', 'member_ids' => [$outsider->id]])
        ->assertHasFormErrors(['member_ids.0']);

    expect(CabBoard::query()->count())->toBe(0);
});

it('creates standard change templates with their plans', function (): void {
    $this->actingAs($this->desk->lead);

    Livewire::test(ManageChangeTemplates::class)
        ->callAction('create', data: ['name' => 'Restart print spooler', 'risk' => 'low', 'description' => 'Printer queue stuck.', 'implementation_plan' => 'Restart the service.', 'rollback_plan' => 'None needed.', 'is_active' => true])
        ->assertHasNoFormErrors()
        ->callAction('create', data: ['name' => 'No plan', 'risk' => 'low', 'description' => 'x'])
        ->assertHasFormErrors(['implementation_plan', 'rollback_plan']);

    expect(ChangeTemplate::query()->sole()->risk)->toBe(ChangeRisk::Low);
});
