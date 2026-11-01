<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\BusinessCalendar;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Filament\Resources\BusinessCalendars\Pages\ManageBusinessCalendars;
use Modules\Ticketing\Application\Sla\CheckSlaDeadlines;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Http\Filament\Pages\SlaTargets;
use Modules\Ticketing\Http\Livewire\Desk\ShowTicket;
use Modules\Ticketing\Http\Livewire\Desk\TicketQueue;
use Modules\Ticketing\Tests\Support\TicketingScenario;

beforeEach(function (): void {
    Notification::fake();
    Filament::setCurrentPanel('admin');
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00', 'Europe/Bucharest'));
    $this->desk = TicketingScenario::create();
    app(OrganizationContext::class)->runAs($this->desk->organization, fn () => $this->desk->lead->givePermissionTo('core.admin.access'));
});

it('stores only SLA targets that differ from the defaults', function (): void {
    $this->actingAs($this->desk->lead);

    Livewire::test(SlaTargets::class)
        ->assertSet('data.critical_response', 15)
        ->set('data.low_response', 120)
        ->call('save')
        ->assertHasNoFormErrors();

    expect(DB::table('ticketing_sla_targets')->pluck('priority')->all())->toBe(['low']);

    Livewire::test(SlaTargets::class)
        ->set('data.high_resolution', 30)
        ->call('save')
        ->assertHasFormErrors(['high_resolution']);
});

it('keeps SLA targets away from agents', function (): void {
    app(OrganizationContext::class)->runAs($this->desk->organization, fn () => $this->desk->agent->givePermissionTo('core.admin.access'));

    $this->actingAs($this->desk->agent)->get('/admin/ticketing/sla-targets')->assertForbidden();
});

it('creates a business calendar from the weekly form', function (): void {
    $admin = $this->desk->member(DefaultRole::Administrator);
    $this->actingAs($admin);

    Livewire::test(ManageBusinessCalendars::class)
        ->callAction('create', data: [
            'name' => 'Support',
            'timezone' => 'Europe/Bucharest',
            'public_holidays' => 'RO',
            'is_default' => true,
            'monday_start' => '08:00', 'monday_end' => '18:00',
            'saturday_start' => '10:00', 'saturday_end' => '14:00',
        ])
        ->assertHasNoFormErrors();

    $calendar = BusinessCalendar::query()->sole();
    expect($calendar->weekly_hours['1'] ?? $calendar->weekly_hours[1])->toBe([['08:00', '18:00']])
        ->and($calendar->weekly_hours['2'] ?? $calendar->weekly_hours[2])->toBe([]);

    Livewire::test(ManageBusinessCalendars::class)
        ->callAction('create', data: ['name' => 'Broken', 'timezone' => 'Europe/Bucharest', 'monday_start' => '18:00', 'monday_end' => '08:00'])
        ->assertHasFormErrors(['monday_end']);
});

it('shows SLA state on the desk and filters tickets at risk', function (): void {
    $calm = $this->desk->ticket(title: 'Calm ticket');
    $urgent = $this->desk->ticket(title: 'Urgent ticket', impact: Impact::High, urgency: Urgency::High);

    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:20', 'Europe/Bucharest'));
    app(OrganizationContext::class)->clear();
    app(CheckSlaDeadlines::class)->handle();
    app(OrganizationContext::class)->set($this->desk->organization);

    $this->actingAs($this->desk->agent);

    Livewire::test(TicketQueue::class)
        ->assertSee(__('ticketing::sla.overdue_since', ['time' => '5m ago']))
        ->set('sla', 'at_risk')
        ->assertSee($urgent->number)
        ->assertDontSee($calm->number);

    Livewire::test(ShowTicket::class, ['ticket' => $urgent])
        ->assertSee(__('ticketing::sla.title'))
        ->assertSee(__('ticketing::sla.metrics.response'));
});
