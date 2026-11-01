<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Filament\Resources\Teams\Pages\ManageTeams;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\Priority;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Models\Category;
use Modules\Ticketing\Domain\Models\Queue;
use Modules\Ticketing\Domain\Workflow\PriorityMatrix as Matrix;
use Modules\Ticketing\Http\Filament\Pages\PriorityMatrix;
use Modules\Ticketing\Http\Filament\Resources\Categories\Pages\ManageCategories;
use Modules\Ticketing\Http\Filament\Resources\Queues\Pages\ManageQueues;
use Modules\Ticketing\Tests\Support\TicketingScenario;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->desk = TicketingScenario::create();
    // Team leads manage ticketing settings but are not panel users by default.
    app(OrganizationContext::class)->runAs($this->desk->organization, fn () => $this->desk->lead->givePermissionTo('core.admin.access'));
    $this->admin = $this->desk->member(DefaultRole::Administrator);
});

it('gives team leads the ticketing settings and keeps agents out', function (): void {
    foreach (['/admin/ticketing/categories', '/admin/ticketing/queues', '/admin/ticketing/priority-matrix'] as $path) {
        $this->actingAs($this->desk->lead)->get($path)->assertOk();
    }

    app(OrganizationContext::class)->runAs($this->desk->organization, fn () => $this->desk->agent->givePermissionTo('core.admin.access'));
    $this->actingAs($this->desk->agent)->get('/admin/ticketing/queues')->assertForbidden();
});

it('creates two-level categories', function (): void {
    $this->actingAs($this->desk->lead);
    $hardware = Category::factory()->create(['name' => 'Hardware']);

    Livewire::test(ManageCategories::class)
        ->callAction('create', data: ['name' => 'Printers', 'parent_id' => $hardware->id, 'is_active' => true, 'sort_order' => 1])
        ->assertHasNoFormErrors();

    $printers = Category::query()->where('name', 'Printers')->sole();
    expect($printers->parent_id)->toBe($hardware->id)
        ->and($printers->fullName())->toBe('Hardware › Printers');

    Livewire::test(ManageCategories::class)
        ->callAction('create', data: ['name' => 'Toner', 'parent_id' => $printers->id])
        ->assertHasFormErrors(['parent_id']);
});

it('moves the default flag when another queue becomes default', function (): void {
    $this->actingAs($this->desk->lead);

    Livewire::test(ManageQueues::class)
        ->callAction('create', data: ['name' => 'Network', 'team_id' => $this->desk->team->id, 'is_default' => true, 'is_active' => true])
        ->assertHasNoFormErrors();

    expect(Queue::query()->where('is_default', true)->pluck('name')->all())->toBe(['Network']);
});

it('refuses a team of another organization for a queue', function (): void {
    $foreignTeam = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => Team::factory()->create());
    $this->actingAs($this->desk->lead);

    Livewire::test(ManageQueues::class)
        ->callAction('create', data: ['name' => 'Elsewhere', 'team_id' => $foreignTeam->id])
        ->assertHasFormErrors(['team_id']);
});

it('stores only priority matrix cells that differ from the default, and audits changes', function (): void {
    $this->actingAs($this->desk->lead);

    $page = Livewire::test(PriorityMatrix::class)->assertSet('data.high_high', 'critical');
    $page->set('data.low_low', 'medium')->call('save')->assertHasNoFormErrors();

    expect(DB::table('ticketing_priority_matrix')->where('organization_id', $this->desk->organization->id)->count())->toBe(1)
        ->and(app(Matrix::class)->resolve($this->desk->organization->id, Impact::Low, Urgency::Low))->toBe(Priority::Medium)
        ->and(AuditLog::query()->where('auditable_id', $this->desk->organization->id)->where('event', AuditEvent::Updated)->exists())->toBeTrue();

    Livewire::test(PriorityMatrix::class)->set('data.low_low', 'low')->call('save');
    expect(DB::table('ticketing_priority_matrix')->count())->toBe(0);
});

it('manages team members through the organization check', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(ManageTeams::class)
        ->callAction('create', data: ['name' => 'Network team', 'member_ids' => [$this->desk->agent->id]])
        ->assertHasNoFormErrors();

    expect(Team::query()->where('name', 'Network team')->sole()->hasMember($this->desk->agent))->toBeTrue();

    $outsider = $this->desk->member(DefaultRole::Agent, Organization::factory()->create());

    Livewire::test(ManageTeams::class)
        ->callAction('create', data: ['name' => 'Mixed team', 'member_ids' => [$outsider->id]])
        ->assertHasFormErrors(['member_ids.0']);

    expect(Team::query()->where('name', 'Mixed team')->exists())->toBeFalse();
});
