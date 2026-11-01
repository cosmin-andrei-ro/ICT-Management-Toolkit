<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Calendar;

use DateTimeImmutable;

/**
 * Legal public holidays in Romania (Labour Code, art. 139), computed per year.
 * Orthodox Easter and Pentecost move every year; Good Friday is a holiday since
 * 2018, and 6–7 January since 2024.
 */
final class RomanianPublicHolidays
{
    /**
     * @return array<string, string> Y-m-d => name
     */
    public static function forYear(int $year): array
    {
        $easter = self::orthodoxEaster($year);
        $offset = fn (int $days): string => $easter->modify(($days >= 0 ? '+' : '').$days.' days')->format('Y-m-d');

        $holidays = [
            "{$year}-01-01" => 'New Year\'s Day',
            "{$year}-01-02" => 'New Year holiday',
            "{$year}-01-24" => 'Union Day',
            $offset(0) => 'Orthodox Easter',
            $offset(1) => 'Orthodox Easter Monday',
            "{$year}-05-01" => 'Labour Day',
            "{$year}-06-01" => 'Children\'s Day',
            $offset(49) => 'Orthodox Pentecost',
            $offset(50) => 'Orthodox Pentecost Monday',
            "{$year}-08-15" => 'Dormition of the Mother of God',
            "{$year}-11-30" => 'Saint Andrew\'s Day',
            "{$year}-12-01" => 'National Day',
            "{$year}-12-25" => 'Christmas Day',
            "{$year}-12-26" => 'Second day of Christmas',
        ];

        if ($year >= 2018) {
            $holidays[$offset(-2)] = 'Orthodox Good Friday';
        }

        if ($year >= 2024) {
            $holidays["{$year}-01-06"] = 'Epiphany';
            $holidays["{$year}-01-07"] = 'Saint John the Baptist';
        }

        ksort($holidays);

        return $holidays;
    }

    /**
     * Meeus' Julian algorithm, converted to the Gregorian calendar (valid 1900–2099).
     */
    public static function orthodoxEaster(int $year): DateTimeImmutable
    {
        $a = $year % 4;
        $b = $year % 7;
        $c = $year % 19;
        $d = (19 * $c + 15) % 30;
        $e = (2 * $a + 4 * $b - $d + 34) % 7;
        $month = intdiv($d + $e + 114, 31);
        $day = (($d + $e + 114) % 31) + 1;

        $julian = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));

        return $julian->modify('+13 days');
    }
}
