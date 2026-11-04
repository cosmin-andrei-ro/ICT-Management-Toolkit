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
use Modules\Ticketing\Domain\Events\OlaBreached;
use Modules\Ticketing\Domain\Models\OlaTimer;
use Modules\Ticketing\Domain\Models\Queue;
use Modules\Ticketing\Domain\Models\TeamOla;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Domain\Sla\SlaOutcome;

/**
 * Runs the clock of the team a ticket is with (ADR 0074), in working time. A team
 * without a target has no clock. Waiting for the requester pauses it, as for SLAs.
 * Moving the ticket to another team ends this clock and starts the next team's.
 */
final readonly class OlaClock
{
    public function __construct(
        private BusinessCalendars $calendars,
        private BusinessTimeCalculator $calculator,
        private Dispatcher $events,
    ) {}

    public function start(Ticket $ticket): void
    {
        $teamId = $this->teamOf($ticket);
        $ola = $teamId !== null ? TeamOla::query()->where('team_id', $teamId)->first() : null;

        if ($ola === null || ! $ticket->type->hasSla() || $this->open($ticket) !== null) {
            return;
        }

        $timer = new OlaTimer;
        $timer->forceFill([
            'organization_id' => $ticket->organization_id,
            'ticket_id' => $ticket->id,
            'team_id' => $teamId,
            'target_minutes' => $ola->minutes,
            'consumed_minutes' => 0,
        ]);
        $this->run($timer, $this->now(), $this->calendars->forCurrentOrganization());
        $timer->save();
    }

    /**
     * The ticket's queue changed. Within one team, the clock goes on.
     */
    public function queueChanged(Ticket $ticket): void
    {
        $open = $this->open($ticket);

        if ($open !== null && $open->team_id === $this->teamOf($ticket)) {
            return;
        }

        $this->complete($ticket, null);
        $this->start($ticket);
    }

    /**
     * @param  ?SlaOutcome  $outcome  Null: met or breached, depending on the time used.
     */
    public function complete(Ticket $ticket, ?SlaOutcome $outcome): void
    {
        $timer = $this->open($ticket);

        if ($timer === null) {
            return;
        }

        $now = $this->now();
        $this->stopRun($timer, $now, $this->calendars->forCurrentOrganization());
        $breached = $timer->breached_at !== null || $timer->consumed_minutes > $timer->target_minutes;
        $timer->completed_at = $now;
        $timer->outcome = $outcome ?? ($breached ? SlaOutcome::Breached : SlaOutcome::Met);
        $timer->save();
    }

    public function pause(Ticket $ticket): void
    {
        $timer = $this->open($ticket);

        if ($timer !== null && $timer->isRunning()) {
            $this->stopRun($timer, $this->now(), $this->calendars->forCurrentOrganization());
            $timer->due_at = null;
            $timer->save();
        }
    }

    public function resume(Ticket $ticket): void
    {
        $timer = $this->open($ticket);

        if ($timer !== null && ! $timer->isRunning()) {
            $this->run($timer, $this->now(), $this->calendars->forCurrentOrganization());
            $timer->save();
        }
    }

    /**
     * Called by the scheduler, in the timer's organization.
     */
    public function checkDeadline(OlaTimer $timer): void
    {
        if ($timer->isRunning() && $timer->breached_at === null && $timer->due_at !== null && $timer->due_at->lte($this->now())) {
            $timer->breached_at = $timer->due_at;
            $timer->save();
            $this->events->dispatch(new OlaBreached($timer));
        }
    }

    private function teamOf(Ticket $ticket): ?string
    {
        if ($ticket->queue_id === null) {
            return null;
        }

        $teamId = Queue::query()->whereKey($ticket->queue_id)->value('team_id');

        return is_string($teamId) ? $teamId : null;
    }

    private function open(Ticket $ticket): ?OlaTimer
    {
        return OlaTimer::query()->where('ticket_id', $ticket->id)->whereNull('completed_at')->first();
    }

    private function run(OlaTimer $timer, CarbonImmutable $now, WorkingSchedule $schedule): void
    {
        $timer->running_since = $now;
        $timer->due_at = $this->normalize($this->calculator->addMinutes($schedule, $now, max(0, $timer->target_minutes - $timer->consumed_minutes)));
    }

    private function stopRun(OlaTimer $timer, CarbonImmutable $now, WorkingSchedule $schedule): void
    {
        if ($timer->running_since !== null) {
            $timer->consumed_minutes += $this->calculator->minutesBetween($schedule, $timer->running_since, $now);
            $timer->running_since = null;
        }
    }

    private function now(): CarbonImmutable
    {
        return $this->normalize(CarbonImmutable::now());
    }

    private function normalize(DateTimeInterface $instant): CarbonImmutable
    {
        $timezone = config('app.timezone');

        return CarbonImmutable::instance($instant)->setTimezone(is_string($timezone) ? $timezone : 'UTC');
    }
}
