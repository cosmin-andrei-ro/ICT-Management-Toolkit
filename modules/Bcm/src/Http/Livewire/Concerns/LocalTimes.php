<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Http\Livewire\Concerns;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Date and time inputs in the display time zone, stored in UTC.
 */
trait LocalTimes
{
    /**
     * Converted to the application's time zone: Eloquent stores the wall-clock
     * time without its offset.
     */
    private function parseLocal(string $value): ?CarbonImmutable
    {
        $instant = $value !== '' ? CarbonImmutable::createFromFormat('Y-m-d\TH:i', $value, self::displayTimezone()) : null;

        return $instant instanceof CarbonImmutable ? $instant->setTimezone(config()->string('app.timezone')) : null;
    }

    private function formatLocal(?CarbonInterface $instant): string
    {
        return $instant !== null ? $instant->copy()->setTimezone(self::displayTimezone())->format('Y-m-d\TH:i') : '';
    }

    private static function displayTimezone(): string
    {
        $timezone = config('app.display_timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
    }
}
