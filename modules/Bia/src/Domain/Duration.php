<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Domain;

/**
 * Recovery objectives are stored in minutes and entered and shown in the
 * largest whole unit: 240 minutes is "4 hours", 4320 is "3 days".
 */
final class Duration
{
    public const array UNITS = ['minutes' => 1, 'hours' => 60, 'days' => 1440];

    /**
     * @return array{value: string, unit: string} For a form; empty for none.
     */
    public static function split(?int $minutes): array
    {
        if ($minutes === null) {
            return ['value' => '', 'unit' => 'hours'];
        }

        foreach (['days', 'hours'] as $unit) {
            if ($minutes > 0 && $minutes % self::UNITS[$unit] === 0) {
                return ['value' => (string) intdiv($minutes, self::UNITS[$unit]), 'unit' => $unit];
            }
        }

        return ['value' => (string) $minutes, 'unit' => 'minutes'];
    }

    public static function toMinutes(string $value, string $unit): ?int
    {
        if (trim($value) === '' || ! ctype_digit(trim($value)) || ! isset(self::UNITS[$unit])) {
            return null;
        }

        return (int) trim($value) * self::UNITS[$unit];
    }

    public static function format(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        $split = self::split($minutes);

        return trans_choice('bia::processes.units.'.$split['unit'], (int) $split['value'], ['count' => $split['value']]);
    }
}
