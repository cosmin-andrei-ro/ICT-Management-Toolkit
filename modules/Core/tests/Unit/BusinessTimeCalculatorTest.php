<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Core\Domain\Calendar\BusinessTimeCalculator;
use Modules\Core\Domain\Calendar\RomanianPublicHolidays;
use Modules\Core\Domain\Calendar\WorkingSchedule;

function at(string $local, string $tz = 'Europe/Bucharest'): DateTimeImmutable
{
    return new DateTimeImmutable($local, new DateTimeZone($tz));
}

function local(DateTimeImmutable $instant, string $tz = 'Europe/Bucharest'): string
{
    return $instant->setTimezone(new DateTimeZone($tz))->format('Y-m-d H:i');
}

beforeEach(function (): void {
    $this->calc = new BusinessTimeCalculator;
    $this->schedule = WorkingSchedule::default();
});

describe('Romanian public holidays', function (): void {
    it('computes Orthodox Easter', function (int $year, string $date): void {
        expect(RomanianPublicHolidays::orthodoxEaster($year)->format('Y-m-d'))->toBe($date);
    })->with([[2024, '2024-05-05'], [2025, '2025-04-20'], [2026, '2026-04-12'], [2027, '2027-05-02']]);

    it('lists the legal holidays of 2026', function (): void {
        expect(array_keys(RomanianPublicHolidays::forYear(2026)))->toBe([
            '2026-01-01', '2026-01-02', '2026-01-06', '2026-01-07', '2026-01-24',
            '2026-04-10', '2026-04-12', '2026-04-13', '2026-05-01', '2026-05-31',
            '2026-06-01', '2026-08-15', '2026-11-30', '2026-12-01', '2026-12-25', '2026-12-26',
        ]);
    });

    it('applies Good Friday only from 2018 and Epiphany only from 2024', function (): void {
        expect(RomanianPublicHolidays::forYear(2017))->not->toContain('Orthodox Good Friday')
            ->and(RomanianPublicHolidays::forYear(2023))->not->toHaveKey('2023-01-06')
            ->and(RomanianPublicHolidays::forYear(2024))->toHaveKey('2024-01-06');
    });
});

describe('adding working minutes', function (): void {
    it('stays within the same day when possible', function (): void {
        expect(local($this->calc->addMinutes($this->schedule, at('2026-11-02 10:00'), 60)))->toBe('2026-11-02 11:00');
    });

    it('carries over to the next working morning', function (): void {
        // Monday 16:00 + 2h = Tuesday 10:00.
        expect(local($this->calc->addMinutes($this->schedule, at('2026-11-02 16:00'), 120)))->toBe('2026-11-03 10:00');
    });

    it('starts at opening time when created outside working hours', function (): void {
        expect(local($this->calc->addMinutes($this->schedule, at('2026-11-02 06:30'), 15)))->toBe('2026-11-02 09:15')
            ->and(local($this->calc->addMinutes($this->schedule, at('2026-11-02 22:00'), 15)))->toBe('2026-11-03 09:15');
    });

    it('skips weekends', function (): void {
        // Friday 16:30 + 1h = Monday 09:30.
        expect(local($this->calc->addMinutes($this->schedule, at('2026-11-06 16:30'), 60)))->toBe('2026-11-09 09:30');
    });

    it('skips public holidays', function (): void {
        // Monday 30 Nov and Tuesday 1 Dec 2026 are holidays: Friday 27 Nov 16:00 + 2h = Wednesday 2 Dec 10:00.
        expect(local($this->calc->addMinutes($this->schedule, at('2026-11-27 16:00'), 120)))->toBe('2026-12-02 10:00');
    });

    it('skips extra holidays of the organization', function (): void {
        $schedule = new WorkingSchedule('Europe/Bucharest', $this->schedule->weeklyHours, ['2026-11-03'], 'RO');

        expect(local($this->calc->addMinutes($schedule, at('2026-11-02 16:00'), 120)))->toBe('2026-11-04 10:00');
    });

    it('spans several days', function (): void {
        // 3 working days of 8h from Monday 09:00 ends Wednesday 17:00.
        expect(local($this->calc->addMinutes($this->schedule, at('2026-11-02 09:00'), 3 * 8 * 60)))->toBe('2026-11-04 17:00');
    });

    it('handles split days (lunch break)', function (): void {
        $day = [['09:00', '12:00'], ['13:00', '17:00']];
        $schedule = new WorkingSchedule('Europe/Bucharest', [1 => $day, 2 => $day, 3 => $day, 4 => $day, 5 => $day]);

        expect(local($this->calc->addMinutes($schedule, at('2026-11-02 11:30'), 60)))->toBe('2026-11-02 13:30');
    });

    it('counts real time across daylight saving changes', function (): void {
        $always = WorkingSchedule::alwaysOpen('Europe/Bucharest');

        // Clocks go back on 25 Oct 2026 at 04:00 → 03:00: 3 real hours later the wall clock shows 02:00 + 2h.
        expect(local($this->calc->addMinutes($always, at('2026-10-25 01:00'), 180)))->toBe('2026-10-25 03:00')
            // Clocks go forward on 29 Mar 2026 at 03:00 → 04:00.
            ->and(local($this->calc->addMinutes($always, at('2026-03-29 01:00'), 180)))->toBe('2026-03-29 05:00');
    });

    it('works in any time zone of the caller', function (): void {
        // 07:00 UTC is 09:00 in Bucharest (UTC+2 in November).
        $result = $this->calc->addMinutes($this->schedule, at('2026-11-02 07:00', 'UTC'), 30);

        expect(local($result, 'UTC'))->toBe('2026-11-02 07:30');
    });

    it('refuses schedules without working time', function (): void {
        $this->calc->addMinutes(new WorkingSchedule('UTC', []), at('2026-11-02 09:00'), 1);
    })->throws(LogicException::class);
});

describe('measuring working minutes', function (): void {
    it('counts only working time between two instants', function (): void {
        // Friday 16:00 → Monday 10:00 = 1h Friday + 1h Monday.
        expect($this->calc->minutesBetween($this->schedule, at('2026-11-06 16:00'), at('2026-11-09 10:00')))->toBe(120);
    });

    it('is the inverse of adding', function (string $start, int $minutes): void {
        $end = $this->calc->addMinutes($this->schedule, at($start), $minutes);
        $effectiveStart = $this->calc->addMinutes($this->schedule, at($start), 0);

        expect($this->calc->minutesBetween($this->schedule, $effectiveStart, $end))->toBe($minutes);
    })->with([
        ['2026-11-02 10:17', 1],
        ['2026-11-06 16:45', 90],
        ['2026-11-27 08:00', 960],
        ['2026-12-24 15:00', 2400],
    ]);

    it('is zero for reversed or empty ranges and outside working hours', function (): void {
        expect($this->calc->minutesBetween($this->schedule, at('2026-11-02 12:00'), at('2026-11-02 11:00')))->toBe(0)
            ->and($this->calc->minutesBetween($this->schedule, at('2026-11-07 09:00'), at('2026-11-08 18:00')))->toBe(0);
    });
});

describe('schedule validation', function (): void {
    it('rejects malformed intervals and unknown holiday calendars', function (array $hours, ?string $holidays): void {
        new WorkingSchedule('Europe/Bucharest', $hours, [], $holidays);
    })->with([
        'end before start' => [[1 => [['17:00', '09:00']]], null],
        'bad time' => [[1 => [['9:00', '17:00']]], null],
        'bad weekday' => [[8 => [['09:00', '17:00']]], null],
        'unknown country' => [[1 => [['09:00', '17:00']]], 'XX'],
    ])->throws(InvalidArgumentException::class);
});
