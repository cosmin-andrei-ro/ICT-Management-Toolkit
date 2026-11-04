<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Application\Actions\AssignTicket;
use Modules\Ticketing\Application\Actions\ChangeTicketStatus;
use Modules\Ticketing\Application\Actions\ReopenTicket;
use Modules\Ticketing\Application\Actions\ResolveTicket;
use Modules\Ticketing\Application\Notifications\TicketUpdate;
use Modules\Ticketing\Application\Sla\CheckSlaDeadlines;
use Modules\Ticketing\Domain\Enums\ResolutionCode;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Models\OlaTimer;
use Modules\Ticketing\Domain\Models\Queue;
use Modules\Ticketing\Domain\Models\SlaTimer;
use Modules\Ticketing\Domain\Models\TeamOla;
use Modules\Ticketing\Domain\Sla\SlaOutcome;
use Modules\Ticketing\Http\Filament\Pages\OlaTargets;
use Modules\Ticketing\Http\Filament\Resources\Queues\Pages\ManageQueues;
use Modules\Ticketing\Tests\Support\TicketingScenario;

beforeEach(function (): void {
    // A Monday morning, inside the default working hours.
    $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00:00', 'UTC'));
    Notification::fake();
    $this->desk = TicketingScenario::create();
    app(OrganizationContext::class)->set($this->desk->organization);
});

function setOla(object $test, int $minutes = 120): void
{
    TeamOla::query()->create(['team_id' => $test->desk->team->id, 'minutes' => $minutes]);
}

it('starts no clock for a team without a target', function (): void {
    $this->desk->ticket();

    expect(OlaTimer::query()->count())->toBe(0);
});

it('runs a clock for the team in working time, and ends it when the ticket is resolved', function (): void {
    setOla($this);
    $ticket = $this->desk->ticket();
    $timer = OlaTimer::query()->where('ticket_id', $ticket->id)->sole();

    expect($timer->team_id)->toBe($this->desk->team->id)->and($timer->target_minutes)->toBe(120)
        ->and($timer->due_at->equalTo(now()->addMinutes(120)))->toBeTrue();

    $this->travel(30)->minutes();
    app(ResolveTicket::class)->handle($ticket->fresh(), ResolutionCode::Fixed, 'Done', $this->desk->agent);

    $timer->refresh();
    expect($timer->outcome)->toBe(SlaOutcome::Met)->and($timer->consumed_minutes)->toBe(30);
});

it('pauses while waiting for the requester', function (): void {
    setOla($this);
    $ticket = $this->desk->ticket();
    app(AssignTicket::class)->handle($ticket, $this->desk->agent, null, $this->desk->lead);
    app(ChangeTicketStatus::class)->handle($ticket->fresh(), TicketStatus::InProgress, $this->desk->agent);
    $this->travel(10)->minutes();
    app(ChangeTicketStatus::class)->handle($ticket->fresh(), TicketStatus::Pending, $this->desk->agent);

    $timer = OlaTimer::query()->sole();
    expect($timer->isRunning())->toBeFalse()->and($timer->consumed_minutes)->toBe(10);

    $this->travel(60)->minutes();
    app(ChangeTicketStatus::class)->handle($ticket->fresh(), TicketStatus::InProgress, $this->desk->agent);
    expect($timer->fresh()->isRunning())->toBeTrue()->and($timer->fresh()->due_at->equalTo(now()->addMinutes(110)))->toBeTrue();
});

it('ends the clock and starts the next team\'s when the ticket changes team, but not within a team', function (): void {
    setOla($this);
    $ticket = $this->desk->ticket();
    $sameTeam = Queue::query()->create(['team_id' => $this->desk->team->id, 'name' => 'Second queue']);
    app(AssignTicket::class)->handle($ticket->fresh(), null, $sameTeam, $this->desk->lead);
    expect(OlaTimer::query()->count())->toBe(1);

    $other = Team::factory()->create();
    TeamOla::query()->create(['team_id' => $other->id, 'minutes' => 300]);
    $otherQueue = Queue::query()->create(['team_id' => $other->id, 'name' => 'Other team']);
    $this->travel(10)->minutes();
    app(AssignTicket::class)->handle($ticket->fresh(), null, $otherQueue, $this->desk->lead);

    $timers = OlaTimer::query()->orderBy('created_at')->orderBy('target_minutes')->get();
    expect($timers)->toHaveCount(2)
        ->and($timers[0]->outcome)->toBe(SlaOutcome::Met)
        ->and($timers[1]->team_id)->toBe($other->id)->and($timers[1]->isRunning())->toBeTrue();
});

it('starts a new clock when a resolved ticket is reopened', function (): void {
    setOla($this);
    $ticket = $this->desk->ticket();
    app(ResolveTicket::class)->handle($ticket->fresh(), ResolutionCode::Fixed, 'Done', $this->desk->agent);
    app(ReopenTicket::class)->handle($ticket->fresh(), 'Still broken', $this->desk->requester);

    expect(OlaTimer::query()->whereNull('completed_at')->count())->toBe(1)->and(OlaTimer::query()->count())->toBe(2);
});

it('tells the team lead once when the target is missed', function (): void {
    setOla($this, 60);
    $this->desk->team->forceFill(['lead_id' => $this->desk->lead->id])->save();
    $this->desk->ticket();
    $this->travel(61)->minutes();

    app(CheckSlaDeadlines::class)->handle();
    app(CheckSlaDeadlines::class)->handle();

    expect(Notification::sent($this->desk->lead, TicketUpdate::class)->filter(fn (TicketUpdate $update): bool => $update->kind === 'ola_breached'))->toHaveCount(1);
    expect(OlaTimer::query()->sole()->breached_at)->not->toBeNull();
});

it('escalates to the queue\'s contact only after the delay, once per clock, moving nothing', function (): void {
    setOla($this, 60);
    $contact = $this->desk->member(DefaultRole::TeamLead);
    $this->desk->queue->update(['escalation_user_id' => $contact->id, 'escalate_after_minutes' => 30]);
    $ticket = $this->desk->ticket();
    app(AssignTicket::class)->handle($ticket->fresh(), $this->desk->agent, null, $this->desk->lead);

    $this->travel(61)->minutes();
    app(CheckSlaDeadlines::class)->handle();
    Notification::assertNotSentTo($contact, TicketUpdate::class, fn (TicketUpdate $update): bool => $update->kind === 'escalated');

    $this->travel(31)->minutes();
    app(CheckSlaDeadlines::class)->handle();
    app(CheckSlaDeadlines::class)->handle();

    $escalations = Notification::sent($contact, TicketUpdate::class)->filter(fn (TicketUpdate $update): bool => $update->kind === 'escalated');
    expect($escalations)->toHaveCount(OlaTimer::query()->whereNotNull('escalated_at')->count() + SlaTimer::query()->whereNotNull('escalated_at')->count())
        ->and(OlaTimer::query()->sole()->escalated_at)->not->toBeNull();

    $ticket->refresh();
    expect($ticket->assignee_id)->toBe($this->desk->agent->id)->and($ticket->queue_id)->toBe($this->desk->queue->id);
});

it('escalates a missed SLA too, and nothing for a queue without a contact', function (): void {
    $contact = $this->desk->member(DefaultRole::TeamLead);
    $ticket = $this->desk->ticket();
    SlaTimer::query()->where('ticket_id', $ticket->id)->update(['due_at' => now()->subHours(3)]);

    app(CheckSlaDeadlines::class)->handle();
    $this->travel(120)->minutes();
    app(CheckSlaDeadlines::class)->handle();
    expect(SlaTimer::query()->whereNotNull('escalated_at')->count())->toBe(0);

    $this->desk->queue->update(['escalation_user_id' => $contact->id, 'escalate_after_minutes' => 30]);
    app(CheckSlaDeadlines::class)->handle();
    expect(SlaTimer::query()->whereNotNull('escalated_at')->count())->toBeGreaterThan(0);
    Notification::assertSentTo($contact, TicketUpdate::class, fn (TicketUpdate $update): bool => $update->kind === 'escalated');
});

it('does not count team clocks in the SLA figures', function (): void {
    setOla($this);
    $ticket = $this->desk->ticket();

    expect(SlaTimer::query()->where('ticket_id', $ticket->id)->count())->toBe(2)
        ->and(OlaTimer::query()->where('ticket_id', $ticket->id)->count())->toBe(1);
});

it('lets people who configure Ticketing set targets, and nobody else', function (): void {
    $this->actingAs($this->desk->lead);
    Livewire::test(OlaTargets::class)
        ->set('data.'.$this->desk->team->id, 90)
        ->call('save')->assertHasNoErrors();
    expect(TeamOla::query()->where('team_id', $this->desk->team->id)->sole()->minutes)->toBe(90);

    Livewire::test(OlaTargets::class)->set('data.'.$this->desk->team->id, 0)->call('save')->assertHasErrors();
    Livewire::test(OlaTargets::class)->set('data.'.$this->desk->team->id, null)->call('save')->assertHasNoErrors();
    expect(TeamOla::query()->count())->toBe(0);

    $this->actingAs($this->desk->agent);
    Livewire::test(OlaTargets::class)->assertForbidden();
});

it('only accepts someone of the organization as escalation contact', function (): void {
    $this->actingAs($this->desk->lead);
    $outsider = Modules\Core\Domain\Models\User::factory()->create();

    Livewire::test(ManageQueues::class)
        ->callTableAction('edit', $this->desk->queue, data: ['escalation_user_id' => $outsider->id])
        ->assertHasTableActionErrors(['escalation_user_id']);

    Livewire::test(ManageQueues::class)
        ->callTableAction('edit', $this->desk->queue, data: ['escalation_user_id' => $this->desk->agent->id, 'escalate_after_minutes' => 45])
        ->assertHasNoTableActionErrors();
    expect($this->desk->queue->fresh()->escalation_user_id)->toBe($this->desk->agent->id);
});
