<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Livewire\Changes;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Changes\ChangeCalendar;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Submitted and approved changes of the next 30 days, by day, with overlaps marked.
 */
#[Layout('core::components.layouts.app')]
final class ChangeCalendarView extends Component
{
    private const int DAYS = 30;

    #[Url]
    public string $from = '';

    public function render(ChangeCalendar $calendar): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->can('ticketing.ticket.view_all'), 403);

        $timezone = config()->string('app.display_timezone');
        $start = $this->startDay($timezone);
        $end = $start->addDays(self::DAYS);

        $changes = $calendar->between($start, $end);
        $conflicting = $changes->filter(fn (Ticket $change): bool => $calendar->conflictsWith($change)->isNotEmpty())->pluck('id')->all();

        $byDay = $changes->groupBy(fn (Ticket $change): string => (string) $change->changeDetails?->planned_start?->timezone($timezone)->format('Y-m-d'));

        return view('ticketing::livewire.changes.calendar', [
            'byDay' => $byDay,
            'conflicting' => $conflicting,
            'start' => $start,
            'end' => $end,
            'timezone' => $timezone,
        ])->title(__('ticketing::changes.ui.calendar'));
    }

    /**
     * The "from" URL parameter is user input: anything but a real Y-m-d date means today.
     */
    private function startDay(string $timezone): CarbonImmutable
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $this->from, new DateTimeZone($timezone));

        return $day !== false && $day->format('Y-m-d') === $this->from
            ? CarbonImmutable::instance($day)
            : CarbonImmutable::now($timezone)->startOfDay();
    }
}
