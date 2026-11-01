<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Cmdb\Application\Actions\ManageRelationships;
use Modules\Cmdb\Domain\Enums\RelationshipType;
use Modules\Cmdb\Domain\Models\CiLink;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Cmdb\Http\Livewire\ShowItem;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Links\NoConfigurationItems;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Application\Changes\ChangePlan;
use Modules\Ticketing\Application\Changes\CreateChange;
use Modules\Ticketing\Application\Changes\SubmitChange;
use Modules\Ticketing\Domain\Changes\ChangeRisk;
use Modules\Ticketing\Domain\Changes\ChangeType;
use Modules\Ticketing\Domain\Models\CabBoard;
use Modules\Ticketing\Http\Livewire\Changes\ReviewChange;
use Modules\Ticketing\Http\Livewire\Desk\ShowTicket;
use Modules\Ticketing\Tests\Support\TicketingScenario;

beforeEach(function (): void {
    Notification::fake();
    $this->desk = TicketingScenario::create();
    $this->host = ConfigurationItem::factory()->create(['name' => 'host-01', 'identifier' => 'HOST-01']);
    $this->vm = ConfigurationItem::factory()->create(['name' => 'payroll-vm', 'criticality' => 'critical']);
    app(ManageRelationships::class)->add($this->vm, RelationshipType::RunsOn, $this->host);
    $this->actingAs($this->desk->agent);
});

it('links affected items to a ticket from the desk, audited, and shows their impact', function (): void {
    $incident = $this->desk->ticket();

    Livewire::test(ShowTicket::class, ['ticket' => $incident])
        ->set('itemSearch', 'HOST-01')
        ->assertSee('+ host-01', false)
        ->call('linkItem', $this->host->id)
        ->assertHasNoErrors()
        ->assertSee('payroll-vm');

    expect(CiLink::query()->where('linkable_id', $incident->id)->sole()->configuration_item_id)->toBe($this->host->id)
        ->and(AuditLog::query()->where('auditable_id', $incident->id)->where('new_values->configuration_items_added', '!=', null)->exists())->toBeTrue();

    Livewire::test(ShowTicket::class, ['ticket' => $incident])->call('unlinkItem', $this->host->id);
    expect(CiLink::query()->count())->toBe(0);
});

it('refuses items of another organization and people who do not work the ticket', function (): void {
    $incident = $this->desk->ticket();
    $theirs = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => ConfigurationItem::factory()->create());

    Livewire::test(ShowTicket::class, ['ticket' => $incident])->call('linkItem', $theirs->id)->assertHasErrors(['itemSearch']);

    $this->actingAs($this->desk->requester);
    Livewire::test(ShowTicket::class, ['ticket' => $incident])->assertForbidden();

    expect(CiLink::query()->count())->toBe(0);
});

it('shows the CAB what a change touches and what it may affect', function (): void {
    $alice = $this->desk->member(DefaultRole::CabMember);
    $cab = CabBoard::query()->create(['name' => 'CAB']);
    $cab->members()->attach([$alice->id]);
    $change = app(CreateChange::class)->handle(ChangeType::Normal, 'Host firmware', 'x', new ChangePlan(ChangeRisk::High, 'Upgrade.', 'Downgrade.', '', CarbonImmutable::parse('2026-11-20 20:00'), CarbonImmutable::parse('2026-11-20 22:00')), $this->desk->agent);
    app(ConfigurationItems::class)->link($change, $this->host->id, $this->desk->agent);
    app(SubmitChange::class)->handle($change->fresh(), $cab, $this->desk->agent);

    $this->actingAs($alice);
    Livewire::test(ReviewChange::class, ['ticket' => $change->fresh()])
        ->assertSee('host-01')
        ->assertSee('payroll-vm');
});

it('lists the tickets of an item, only those the viewer may see', function (): void {
    $incident = $this->desk->ticket(title: 'Host down');
    app(ConfigurationItems::class)->link($incident, $this->host->id, $this->desk->agent);

    Livewire::test(ShowItem::class, ['item' => $this->host])->assertSee($incident->number.' Host down');

    $cab = $this->desk->member(DefaultRole::CabMember);
    $this->actingAs($cab);
    Livewire::test(ShowItem::class, ['item' => $this->host])->assertDontSee($incident->number);
});

it('hides the panel when the CMDB module is disabled', function (): void {
    app()->bind(ConfigurationItems::class, NoConfigurationItems::class);

    Livewire::test(ShowTicket::class, ['ticket' => $this->desk->ticket()])->assertDontSee(__('ticketing::ui.items.title'));
});
