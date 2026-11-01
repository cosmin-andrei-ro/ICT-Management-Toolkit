<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Ticketing\Application\Problems\CreateProblem;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Http\Livewire\Desk\KnownErrors;
use Modules\Ticketing\Http\Livewire\Desk\ShowTicket;
use Modules\Ticketing\Http\Livewire\Desk\TicketQueue;
use Modules\Ticketing\Tests\Support\TicketingScenario;

beforeEach(function (): void {
    Notification::fake();
    $this->desk = TicketingScenario::create();
    $this->actingAs($this->desk->agent);
});

it('opens a problem from an incident and links them', function (): void {
    $incident = $this->desk->ticket(title: 'VPN drops every hour');

    Livewire::test(ShowTicket::class, ['ticket' => $incident])
        ->call('createProblemFromIncident')
        ->assertRedirect();

    $problem = Ticket::query()->where('type', TicketType::Problem)->sole();
    expect($problem->title)->toBe('VPN drops every hour')
        ->and($incident->fresh()->problem_id)->toBe($problem->id);
});

it('links an incident to an existing problem and unlinks it', function (): void {
    $problem = app(CreateProblem::class)->handle('VPN concentrator overloaded', 'x', Impact::High, Urgency::High, $this->desk->agent);
    $incident = $this->desk->ticket();

    Livewire::test(ShowTicket::class, ['ticket' => $incident])
        ->assertSee($problem->number)
        ->set('problemId', $problem->id)
        ->call('linkProblem')
        ->assertHasNoErrors();
    expect($incident->fresh()->problem_id)->toBe($problem->id);

    Livewire::test(ShowTicket::class, ['ticket' => $incident->fresh()])->call('unlinkProblem');
    expect($incident->fresh()->problem_id)->toBeNull();
});

it('documents the analysis, publishes the known error and finds it in the KEDB', function (): void {
    $problem = app(CreateProblem::class)->handle('Printers offline after update', 'x', Impact::Medium, Urgency::Medium, $this->desk->agent, [$this->desk->ticket()]);
    app(CreateProblem::class)->handle('Unrelated problem', 'x', Impact::Low, Urgency::Low, $this->desk->agent);

    Livewire::test(ShowTicket::class, ['ticket' => $problem])
        ->set('rootCause', 'Driver 4.2 breaks the spooler.')
        ->set('workaround', 'Roll back the driver to 4.1.')
        ->call('saveAnalysis')
        ->call('markKnownError')
        ->assertHasNoErrors();

    Livewire::test(KnownErrors::class)
        ->assertSee($problem->number)
        ->assertSee('Roll back the driver')
        ->assertDontSee('Unrelated problem')
        ->set('search', 'spooler')
        ->assertSee($problem->number)
        ->set('search', 'firewall')
        ->assertDontSee($problem->number);
});

it('resolves a problem and its open incidents from the desk', function (): void {
    $incident = $this->desk->ticket();
    $problem = app(CreateProblem::class)->handle('Mail server overloaded', 'x', Impact::High, Urgency::Medium, $this->desk->agent, [$incident]);

    Livewire::test(ShowTicket::class, ['ticket' => $problem])
        ->assertSet('resolveIncidents', true)
        ->set('resolutionCode', 'fixed')
        ->set('resolutionNote', 'Added storage.')
        ->call('resolve')
        ->assertHasNoErrors();

    expect($problem->fresh()->status)->toBe(TicketStatus::Resolved)
        ->and($incident->fresh()->status)->toBe(TicketStatus::Resolved);
});

it('filters the desk by ticket type', function (): void {
    $incident = $this->desk->ticket();
    $problem = app(CreateProblem::class)->handle('Recurring outage', 'x', Impact::High, Urgency::High, $this->desk->agent);

    Livewire::test(TicketQueue::class)
        ->set('type', 'problem')
        ->assertSee($problem->number)
        ->assertDontSee($incident->number);
});

it('keeps the KEDB and problem actions away from requesters', function (): void {
    $this->actingAs($this->desk->requester)->get(route('ticketing.desk.known-errors'))->assertForbidden();

    $problem = app(CreateProblem::class)->handle('Internal', 'x', Impact::Low, Urgency::Low, $this->desk->agent);
    $this->actingAs($this->desk->requester)->get(route('ticketing.portal.show', $problem))->assertForbidden();
});
