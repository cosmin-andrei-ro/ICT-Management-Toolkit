<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Calendar;

use DateTimeImmutable;
use DateTimeInterface;
use LogicException;

/**
 * Arithmetic on working time. Intervals are built in the schedule's local time and
 * compared as absolute instants, so daylight saving changes are handled naturally.
 */
final class BusinessTimeCalculator
{
    /** Ten years: a guard against schedules with almost no working time. */
    private const int MAX_DAYS = 3660;

    /**
     * The instant when $minutes of working time will have passed after $start.
     */
    public function addMinutes(WorkingSchedule $schedule, DateTimeInterface $start, int $minutes): DateTimeImmutable
    {
        $cursor = DateTimeImmutable::createFromInterface($start)->setTimezone($schedule->timezone);
        $remaining = max(0, $minutes) * 60;

        if (! $schedule->hasWorkingTime()) {
            throw new LogicException('The schedule has no working time.');
        }

        $day = $cursor->setTime(0, 0);

        for ($i = 0; $i < self::MAX_DAYS; $i++, $day = $day->modify('+1 day')) {
            foreach ($schedule->intervalsOn($day) as [$from, $to]) {
                $segmentStart = max($from, $cursor);

                if ($segmentStart >= $to) {
                    continue;
                }

                $available = $to->getTimestamp() - $segmentStart->getTimestamp();

                if ($remaining <= $available) {
                    // On the timestamp: modify('+N seconds') follows the wall clock across DST changes.
                    return (new DateTimeImmutable('@'.($segmentStart->getTimestamp() + $remaining)))->setTimezone($schedule->timezone);
                }

                $remaining -= $available;
            }
        }

        throw new LogicException('No working time found within ten years.');
    }

    /**
     * Whole minutes of working time between two instants (0 when $end <= $start).
     */
    public function minutesBetween(WorkingSchedule $schedule, DateTimeInterface $start, DateTimeInterface $end): int
    {
        $from = DateTimeImmutable::createFromInterface($start)->setTimezone($schedule->timezone);
        $until = DateTimeImmutable::createFromInterface($end)->setTimezone($schedule->timezone);

        if ($until <= $from) {
            return 0;
        }

        $seconds = 0;
        $day = $from->setTime(0, 0);

        while ($day < $until) {
            foreach ($schedule->intervalsOn($day) as [$intervalStart, $intervalEnd]) {
                $overlapStart = max($intervalStart, $from);
                $overlapEnd = min($intervalEnd, $until);

                if ($overlapEnd > $overlapStart) {
                    $seconds += $overlapEnd->getTimestamp() - $overlapStart->getTimestamp();
                }
            }

            $day = $day->modify('+1 day');
        }

        return intdiv($seconds, 60);
    }
}
