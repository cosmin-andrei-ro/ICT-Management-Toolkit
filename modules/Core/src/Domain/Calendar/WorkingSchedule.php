<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * When work happens: weekly hours in a time zone, minus holidays. Immutable and
 * free of persistence, so business-time arithmetic can be tested in isolation.
 */
final readonly class WorkingSchedule
{
    public DateTimeZone $timezone;

    /**
     * @param  array<int, list<array{0: string, 1: string}>>  $weeklyHours  ISO weekday (1 = Monday) => intervals "HH:MM"
     * @param  list<string>  $extraHolidays  Y-m-d dates without work, besides public holidays
     * @param  ?string  $publicHolidays  Country whose public holidays apply ("RO"), or null
     */
    public function __construct(
        string $timezone,
        public array $weeklyHours,
        public array $extraHolidays = [],
        public ?string $publicHolidays = null,
    ) {
        $this->timezone = new DateTimeZone($timezone);

        foreach ($weeklyHours as $weekday => $intervals) {
            if ($weekday < 1 || $weekday > 7) {
                throw new InvalidArgumentException("Weekday must be 1–7, [{$weekday}] given.");
            }

            foreach ($intervals as [$start, $end]) {
                if (preg_match('/\A([01]\d|2[0-3]):[0-5]\d\z/', $start) !== 1 || preg_match('/\A(([01]\d|2[0-3]):[0-5]\d|24:00)\z/', $end) !== 1 || $start >= $end) {
                    throw new InvalidArgumentException("Invalid working interval [{$start}–{$end}].");
                }
            }
        }

        if ($publicHolidays !== null && $publicHolidays !== 'RO') {
            throw new InvalidArgumentException("Unsupported public holiday calendar [{$publicHolidays}].");
        }
    }

    /**
     * Monday to Friday 09:00–17:00, Europe/Bucharest, Romanian public holidays.
     */
    public static function default(): self
    {
        $day = [['09:00', '17:00']];

        return new self('Europe/Bucharest', [1 => $day, 2 => $day, 3 => $day, 4 => $day, 5 => $day], [], 'RO');
    }

    /**
     * Every minute counts (24/7).
     */
    public static function alwaysOpen(string $timezone = 'UTC'): self
    {
        $day = [['00:00', '24:00']];

        return new self($timezone, array_fill(1, 7, $day));
    }

    public function hasWorkingTime(): bool
    {
        foreach ($this->weeklyHours as $intervals) {
            if ($intervals !== []) {
                return true;
            }
        }

        return false;
    }

    public function isHoliday(string $date): bool
    {
        if (in_array($date, $this->extraHolidays, true)) {
            return true;
        }

        return $this->publicHolidays === 'RO'
            && array_key_exists($date, RomanianPublicHolidays::forYear((int) substr($date, 0, 4)));
    }

    /**
     * Working intervals of a local calendar day, as absolute instants.
     *
     * @param  DateTimeImmutable  $day  Any instant on that day, in the schedule's time zone.
     * @return list<array{0: DateTimeImmutable, 1: DateTimeImmutable}>
     */
    public function intervalsOn(DateTimeImmutable $day): array
    {
        $date = $day->setTimezone($this->timezone)->format('Y-m-d');

        if ($this->isHoliday($date)) {
            return [];
        }

        $weekday = (int) $day->setTimezone($this->timezone)->format('N');
        $intervals = [];

        foreach ($this->weeklyHours[$weekday] ?? [] as [$start, $end]) {
            $from = new DateTimeImmutable("{$date} {$start}", $this->timezone);
            $to = $end === '24:00'
                ? (new DateTimeImmutable("{$date} 00:00", $this->timezone))->modify('+1 day')
                : new DateTimeImmutable("{$date} {$end}", $this->timezone);

            $intervals[] = [$from, $to];
        }

        usort($intervals, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return $intervals;
    }
}
