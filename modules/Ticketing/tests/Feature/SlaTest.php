<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Application\Actions\AddTicketComment;
use Modules\Ticketing\Application\Actions\AssignTicket;
use Modules\Ticketing\Application\Actions\CancelTicket;
use Modules\Ticketing\Application\Actions\ChangeTicketStatus;
use Modules\Ticketing\Application\Actions\ReopenTicket;
use Modules\Ticketing\Application\Actions\ResolveTicket;
use Modules\Ticketing\Application\Actions\UpdateTicketDetails;
use Modules\Ticketing\Application\Notifications\TicketUpdate;
use Modules\Ticketing\Application\Sla\CheckSlaDeadlines;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\ResolutionCode;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Models\SlaTimer;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Domain\Sla\SlaMetric;
use Modules\Ticketing\Domain\Sla\SlaOutcome;
use Modules\Ticketing\Tests\Support\TicketingScenario;

/**
 * Wall-clock time in Bucharest; the default schedule is Mon–Fri 09–17 with RO holidays.
 */
function bucharest(string $local): CarbonImmutable
{
    return CarbonImmutable::parse($local, 'Europe/Bucharest');
}

function clockOf(Ticket $ticket, SlaMetric $metric): SlaTimer
{
    return SlaTimer::query()->where('ticket_id', $ticket->id)->where('metric', $metric)->sole();
}

function dueLocal(SlaTimer $timer): ?string
{
    return $timer->due_at?->setTimezone('Europe/Bucharest')->format('Y-m-d H:i');
}

beforeEach(function (): void {
    Notification::fake();
    $this->travelTo(bucharest('2026-11-02 10:00')); // a Monday
    $this->desk = TicketingScenario::create();
});

it('starts both clocks with targets from the ticket priority, in working time', function (): void {
    $ticket = $this->desk->ticket(); // low priority: 8h response, 40h resolution

    expect(dueLocal(clockOf($ticket, SlaMetric::Response)))->toBe('2026-11-03 10:00')
        ->and(dueLocal(clockOf($ticket, SlaMetric::Resolution)))->toBe('2026-11-09 10:00')
        ->and(clockOf($ticket, SlaMetric::Response)->warn_at?->setTimezone('Europe/Bucharest')->format('Y-m-d H:i'))->toBe('2026-11-02 16:00');
});

it('uses critical targets for critical tickets', function (): void {
    $ticket = $this->desk->ticket(impact: Impact::High, urgency: Urgency::High);

    expect(dueLocal(clockOf($ticket, SlaMetric::Response)))->toBe('2026-11-02 10:15')
        ->and(dueLocal(clockOf($ticket, SlaMetric::Resolution)))->toBe('2026-11-02 14:00');
});

it('uses organization overrides', function (): void {
    DB::table('ticketing_sla_targets')->insert([
        'organization_id' => $this->desk->organization->id, 'priority' => 'low', 'response_minutes' => 30, 'resolution_minutes' => 120,
    ]);

    expect(dueLocal(clockOf($this->desk->ticket(), SlaMetric::Response)))->toBe('2026-11-02 10:30');
});

it('completes the response clock on the first public reply from the service desk', function (): void {
    $ticket = $this->desk->ticket();
    $this->travelTo(bucharest('2026-11-02 11:00'));

    app(AddTicketComment::class)->handle($ticket, $this->desk->agent, 'internal', internal: true);
    expect(clockOf($ticket, SlaMetric::Response)->completed_at)->toBeNull();

    app(AddTicketComment::class)->handle($ticket, $this->desk->agent, 'On it.', internal: false);
    $response = clockOf($ticket, SlaMetric::Response);

    expect($response->outcome)->toBe(SlaOutcome::Met)
        ->and($response->consumed_minutes)->toBe(60)
        ->and(clockOf($ticket, SlaMetric::Resolution)->isRunning())->toBeTrue();
});

it('pauses while waiting for the requester and shifts the deadline', function (): void {
    $ticket = $this->desk->ticket();
    app(ChangeTicketStatus::class)->handle($ticket, TicketStatus::InProgress, $this->desk->agent);

    $this->travelTo(bucharest('2026-11-02 12:00'));
    app(ChangeTicketStatus::class)->handle($ticket, TicketStatus::Pending, $this->desk->agent);

    $paused = clockOf($ticket, SlaMetric::Resolution);
    expect($paused->isRunning())->toBeFalse()
        ->and($paused->due_at)->toBeNull()
        ->and($paused->consumed_minutes)->toBe(120);

    // The requester answers the next morning; 2 of 40 working hours are used.
    $this->travelTo(bucharest('2026-11-03 09:30'));
    app(AddTicketComment::class)->handle($ticket, $this->desk->requester, 'Here is the screenshot.', internal: false);

    expect($ticket->fresh()->status)->toBe(TicketStatus::InProgress)
        ->and(dueLocal(clockOf($ticket, SlaMetric::Resolution)))->toBe('2026-11-09 15:30');
});

it('records met and breached outcomes on resolution', function (): void {
    $onTime = $this->desk->ticket();
    $late = $this->desk->ticket(impact: Impact::High, urgency: Urgency::High);

    $this->travelTo(bucharest('2026-11-02 15:00'));
    app(ResolveTicket::class)->handle($onTime, ResolutionCode::Fixed, 'Done', $this->desk->agent);
    app(ResolveTicket::class)->handle($late, ResolutionCode::Fixed, 'Done', $this->desk->agent);

    expect(clockOf($onTime, SlaMetric::Resolution)->outcome)->toBe(SlaOutcome::Met)
        ->and(clockOf($late, SlaMetric::Resolution)->outcome)->toBe(SlaOutcome::Breached)
        ->and(clockOf($late, SlaMetric::Response)->outcome)->toBe(SlaOutcome::Breached);
});

it('restarts the resolution clock on reopen with the time already used', function (): void {
    $ticket = $this->desk->ticket();
    $this->travelTo(bucharest('2026-11-02 12:00'));
    app(ResolveTicket::class)->handle($ticket, ResolutionCode::Fixed, 'Done', $this->desk->agent);

    $this->travelTo(bucharest('2026-11-04 10:00'));
    app(ReopenTicket::class)->handle($ticket, 'Still broken.', $this->desk->requester);

    $resolution = clockOf($ticket, SlaMetric::Resolution);
    expect($resolution->completed_at)->toBeNull()
        ->and($resolution->consumed_minutes)->toBe(120)
        ->and(dueLocal($resolution))->toBe('2026-11-10 16:00')
        ->and(clockOf($ticket, SlaMetric::Response)->isCompleted())->toBeTrue();
});

it('does not count cancelled tickets as breaches', function (): void {
    $ticket = $this->desk->ticket(impact: Impact::High, urgency: Urgency::High);
    $this->travelTo(bucharest('2026-11-03 10:00'));

    app(CancelTicket::class)->handle($ticket, 'Duplicate', $this->desk->agent);

    expect(SlaTimer::query()->where('ticket_id', $ticket->id)->pluck('outcome')->unique()->all())->toBe([SlaOutcome::Cancelled]);
});

it('retargets running clocks when triage changes the priority', function (): void {
    $ticket = $this->desk->ticket();
    $this->travelTo(bucharest('2026-11-02 11:00'));

    app(UpdateTicketDetails::class)->handle($ticket, $ticket->title, $ticket->description, Impact::High, Urgency::High, null);

    // Critical: 4h resolution, 1h already used → 14:00.
    expect(clockOf($ticket, SlaMetric::Resolution)->target_minutes)->toBe(240)
        ->and(dueLocal(clockOf($ticket, SlaMetric::Resolution)))->toBe('2026-11-02 14:00');
});

describe('deadline job', function (): void {
    it('warns at 75% and records the breach at the due time, once', function (): void {
        $ticket = $this->desk->ticket(impact: Impact::High, urgency: Urgency::High);
        app(AssignTicket::class)->handle($ticket, $this->desk->agent, null, $this->desk->lead);
        app(OrganizationContext::class)->clear();

        $this->travelTo(bucharest('2026-11-02 10:12')); // response: 15 min, warning at 12 min
        app(CheckSlaDeadlines::class)->handle();
        Notification::assertSentTo($this->desk->agent, TicketUpdate::class, fn (TicketUpdate $n): bool => $n->kind === 'sla_response_warning');

        $this->travelTo(bucharest('2026-11-02 10:20'));
        app(CheckSlaDeadlines::class)->handle();
        app(CheckSlaDeadlines::class)->handle();

        // One warning and one breach, however often the job runs.
        expect(Notification::sent($this->desk->agent, TicketUpdate::class)->filter(fn (TicketUpdate $n): bool => str_starts_with($n->kind, 'sla_'))->count())->toBe(2);
        Notification::assertSentTo($this->desk->agent, TicketUpdate::class, fn (TicketUpdate $n): bool => $n->kind === 'sla_response_breached');

        app(OrganizationContext::class)->set($this->desk->organization);
        expect(clockOf($ticket, SlaMetric::Response)->breached_at?->setTimezone('Europe/Bucharest')->format('H:i'))->toBe('10:15');
    });

    it('tells the whole queue team when nobody owns the ticket', function (): void {
        $this->desk->ticket(impact: Impact::High, urgency: Urgency::High);
        app(OrganizationContext::class)->clear();

        $this->travelTo(bucharest('2026-11-02 10:20'));
        app(CheckSlaDeadlines::class)->handle();

        Notification::assertSentTo([$this->desk->agent, $this->desk->lead], TicketUpdate::class, fn (TicketUpdate $n): bool => $n->kind === 'sla_response_breached');
        Notification::assertNotSentTo($this->desk->requester, TicketUpdate::class, fn (TicketUpdate $n): bool => str_starts_with($n->kind, 'sla_'));
    });

    it('ignores paused clocks', function (): void {
        $ticket = $this->desk->ticket(impact: Impact::High, urgency: Urgency::High);
        app(ChangeTicketStatus::class)->handle($ticket, TicketStatus::InProgress, $this->desk->agent);
        app(ChangeTicketStatus::class)->handle($ticket, TicketStatus::Pending, $this->desk->agent);
        app(OrganizationContext::class)->clear();

        $this->travelTo(bucharest('2026-11-05 10:00'));

        expect(app(CheckSlaDeadlines::class)->handle())->toBe(0);
    });
});

it('enforces timer consistency in the database', function (): void {
    $ticket = $this->desk->ticket();

    DB::table('ticketing_sla_timers')->where('ticket_id', $ticket->id)->update(['completed_at' => now()]);
})->throws(Illuminate\Database\QueryException::class);
