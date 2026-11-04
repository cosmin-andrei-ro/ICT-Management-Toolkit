<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Sla;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Core\Domain\Calendar\BusinessCalendars;
use Modules\Core\Domain\Calendar\BusinessTimeCalculator;
use Modules\Core\Domain\Calendar\WorkingSchedule;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Events\SlaBreached;
use Modules\Ticketing\Domain\Events\SlaWarning;
use Modules\Ticketing\Domain\Models\SlaTimer;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Domain\Sla\SlaMetric;
use Modules\Ticketing\Domain\Sla\SlaOutcome;
use Modules\Ticketing\Domain\Sla\SlaTargets;

/**
 * Runs a ticket's response and resolution clocks in working time (ADR 0010).
 *
 * A clock keeps the working minutes consumed before its current run; while it
 * runs, due_at and warn_at are computed from the remaining minutes. Waiting for
 * the requester (pending) pauses the clocks. Callers run inside the ticket's
 * transaction and organization context.
 */
final readonly class SlaClock
{
    /** Share of the target after which agents are warned. */
    private const float WARNING_RATIO = 0.75;

    public function __construct(
        private SlaTargets $targets,
        private BusinessCalendars $calendars,
        private BusinessTimeCalculator $calculator,
        private Dispatcher $events,
        private OlaClock $ola,
    ) {}

    public function start(Ticket $ticket): void
    {
        // Problems are investigated, not answered to a requester: no service level applies.
        if (! $ticket->type->hasSla()) {
            return;
        }

        $this->ola->start($ticket);

        $now = $this->now();
        $target = $this->targets->resolve($ticket->organization_id, $ticket->priority);
        $schedule = $this->calendars->forCurrentOrganization();

        foreach (SlaMetric::cases() as $metric) {
            $timer = new SlaTimer;
            $timer->forceFill([
                'organization_id' => $ticket->organization_id,
                'ticket_id' => $ticket->id,
                'metric' => $metric,
                'target_minutes' => $target->minutesFor($metric),
                'consumed_minutes' => 0,
            ]);

            $this->run($timer, $now, $schedule);
            $timer->save();
        }
    }

    /**
     * Applies the effect of a status change on the clocks.
     */
    public function onTransition(Ticket $ticket, TicketStatus $from, TicketStatus $to): void
    {
        match (true) {
            $to === TicketStatus::Cancelled => $this->ola->complete($ticket, SlaOutcome::Cancelled),
            $to === TicketStatus::Resolved => $this->ola->complete($ticket, null),
            $to === TicketStatus::Pending || $to === TicketStatus::AwaitingApproval => $this->ola->pause($ticket),
            $from === TicketStatus::Pending || $from === TicketStatus::AwaitingApproval => $this->ola->resume($ticket),
            $from === TicketStatus::Resolved && $to !== TicketStatus::Closed => $this->ola->start($ticket),
            default => null,
        };

        match (true) {
            $to === TicketStatus::Cancelled => $this->completeOpen($ticket, SlaOutcome::Cancelled),
            $to === TicketStatus::Resolved => $this->completeOpen($ticket, null),
            $to === TicketStatus::Pending || $to === TicketStatus::AwaitingApproval => $this->pause($ticket),
            $from === TicketStatus::Pending || $from === TicketStatus::AwaitingApproval => $this->resume($ticket),
            $from === TicketStatus::Resolved && $to !== TicketStatus::Closed => $this->reopen($ticket),
            default => null,
        };
    }

    /**
     * IT support answered the requester for the first time.
     */
    public function responded(Ticket $ticket): void
    {
        $timer = $this->timer($ticket, SlaMetric::Response);

        if ($timer !== null && ! $timer->isCompleted()) {
            $this->complete($timer, null, $this->now(), $this->calendars->forCurrentOrganization());
        }
    }

    /**
     * Priority changed: clocks keep the time used so far against the new targets.
     * A breach already recorded stays recorded.
     */
    public function retarget(Ticket $ticket): void
    {
        $now = $this->now();
        $target = $this->targets->resolve($ticket->organization_id, $ticket->priority);
        $schedule = $this->calendars->forCurrentOrganization();

        foreach ($this->openTimers($ticket) as $timer) {
            $wasRunning = $timer->isRunning();

            if ($wasRunning) {
                $this->stopRun($timer, $now, $schedule);
            }

            $timer->target_minutes = $target->minutesFor($timer->metric);
            $timer->warned_at = null;

            if ($wasRunning) {
                $this->run($timer, $now, $schedule);
            }

            $timer->save();
        }
    }

    /**
     * Marks warnings and breaches whose time has come; called by the scheduler.
     * Runs per timer in the timer's organization.
     */
    public function checkDeadline(SlaTimer $timer): void
    {
        $now = $this->now();

        if (! $timer->isRunning()) {
            return;
        }

        if ($timer->warned_at === null && $timer->warn_at !== null && $timer->warn_at->lte($now)) {
            $timer->warned_at = $now;
            $timer->save();
            $this->events->dispatch(new SlaWarning($timer));
        }

        if ($timer->breached_at === null && $timer->due_at !== null && $timer->due_at->lte($now)) {
            $timer->breached_at = $timer->due_at;
            $timer->save();
            $this->events->dispatch(new SlaBreached($timer));
        }
    }

    private function pause(Ticket $ticket): void
    {
        $now = $this->now();
        $schedule = $this->calendars->forCurrentOrganization();

        foreach ($this->openTimers($ticket) as $timer) {
            if ($timer->isRunning()) {
                $this->stopRun($timer, $now, $schedule);
                // A paused clock has no deadline; a completed one keeps its last as history.
                $timer->due_at = null;
                $timer->save();
            }
        }
    }

    private function resume(Ticket $ticket): void
    {
        $now = $this->now();
        $schedule = $this->calendars->forCurrentOrganization();

        foreach ($this->openTimers($ticket) as $timer) {
            if (! $timer->isRunning()) {
                $this->run($timer, $now, $schedule);
                $timer->save();
            }
        }
    }

    private function reopen(Ticket $ticket): void
    {
        $timer = $this->timer($ticket, SlaMetric::Resolution);

        if ($timer === null || ! $timer->isCompleted()) {
            return;
        }

        $timer->completed_at = null;
        $timer->outcome = null;
        $this->run($timer, $this->now(), $this->calendars->forCurrentOrganization());
        $timer->save();
    }

    private function completeOpen(Ticket $ticket, ?SlaOutcome $outcome): void
    {
        $now = $this->now();
        $schedule = $this->calendars->forCurrentOrganization();

        foreach ($this->openTimers($ticket) as $timer) {
            $this->complete($timer, $outcome, $now, $schedule);
        }
    }

    /**
     * @param  ?SlaOutcome  $outcome  Null: met or breached, depending on the time used.
     */
    private function complete(SlaTimer $timer, ?SlaOutcome $outcome, CarbonImmutable $now, WorkingSchedule $schedule): void
    {
        if ($timer->isRunning()) {
            $this->stopRun($timer, $now, $schedule);
        }

        $breached = $timer->breached_at !== null || $timer->consumed_minutes > $timer->target_minutes;

        $timer->completed_at = $now;
        $timer->outcome = $outcome ?? ($breached ? SlaOutcome::Breached : SlaOutcome::Met);
        $timer->save();
    }

    private function run(SlaTimer $timer, CarbonImmutable $now, WorkingSchedule $schedule): void
    {
        $remaining = max(0, $timer->target_minutes - $timer->consumed_minutes);
        $untilWarning = max(0, (int) ceil($timer->target_minutes * self::WARNING_RATIO) - $timer->consumed_minutes);

        $timer->running_since = $now;
        $timer->due_at = $this->normalize($this->calculator->addMinutes($schedule, $now, $remaining));
        $timer->warn_at = $timer->warned_at === null
            ? $this->normalize($this->calculator->addMinutes($schedule, $now, $untilWarning))
            : null;
    }

    private function stopRun(SlaTimer $timer, CarbonImmutable $now, WorkingSchedule $schedule): void
    {
        if ($timer->running_since === null) {
            return;
        }

        $timer->consumed_minutes += $this->calculator->minutesBetween($schedule, $timer->running_since, $now);
        $timer->running_since = null;
        $timer->warn_at = null;
    }

    private function timer(Ticket $ticket, SlaMetric $metric): ?SlaTimer
    {
        return SlaTimer::query()->where('ticket_id', $ticket->id)->where('metric', $metric)->first();
    }

    /**
     * @return iterable<SlaTimer>
     */
    private function openTimers(Ticket $ticket): iterable
    {
        return SlaTimer::query()->where('ticket_id', $ticket->id)->whereNull('completed_at')->get();
    }

    private function now(): CarbonImmutable
    {
        return $this->normalize(CarbonImmutable::now());
    }

    /**
     * Eloquent writes timestamps without an offset, so a deadline computed in the
     * calendar's time zone must be converted to the application's before saving.
     */
    private function normalize(DateTimeInterface $instant): CarbonImmutable
    {
        $timezone = config('app.timezone');

        return CarbonImmutable::instance($instant)->setTimezone(is_string($timezone) ? $timezone : 'UTC');
    }
}
