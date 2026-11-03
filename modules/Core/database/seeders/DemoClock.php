<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

/**
 * Demo data happens over the last 90 days: the clock is set to the moment
 * each step happened, so records carry the dates they would have had.
 */
final class DemoClock
{
    public static function at(CarbonImmutable $moment): void
    {
        Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);
    }

    public static function daysAgo(int $days, string $time = '09:00'): CarbonImmutable
    {
        return self::today()->subDays($days)->setTimeFromTimeString($time);
    }

    public static function today(): CarbonImmutable
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        return CarbonImmutable::today();
    }

    public static function reset(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }
}
