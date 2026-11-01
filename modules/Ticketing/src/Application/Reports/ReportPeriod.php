<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Reports;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Whole days, in the display time zone, from the first day to the last day
 * included. Stored and passed around as Y-m-d strings.
 */
final readonly class ReportPeriod
{
    public const int MAX_DAYS = 366;

    private function __construct(
        public string $firstDay,
        public string $lastDay,
        public CarbonImmutable $start,
        /** Exclusive: midnight after the last day. */
        public CarbonImmutable $end,
    ) {}

    public static function between(string $firstDay, string $lastDay, string $timezone): self
    {
        $start = self::day($firstDay, $timezone);
        $last = self::day($lastDay, $timezone);

        if ($last < $start) {
            throw new InvalidArgumentException('The period ends before it starts.');
        }

        $end = $last->addDay();

        if ($start->diffInDays($end) > self::MAX_DAYS) {
            throw new InvalidArgumentException('The period is longer than a year.');
        }

        return new self($firstDay, $lastDay, $start, $end);
    }

    public static function lastDays(int $days, string $timezone): self
    {
        $today = CarbonImmutable::now($timezone)->startOfDay();

        return self::between($today->subDays($days - 1)->format('Y-m-d'), $today->format('Y-m-d'), $timezone);
    }

    private static function day(string $value, string $timezone): CarbonImmutable
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone($timezone));

        if ($day === false || $day->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("Not a date: [{$value}].");
        }

        return CarbonImmutable::instance($day);
    }
}
