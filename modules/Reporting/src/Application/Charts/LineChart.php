<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting\Application\Charts;

/**
 * The geometry of a line chart drawn as SVG on the server (ADR 0045): no
 * script, so it shows the same on screen, in print and in PDFs. Days without
 * a value break the line instead of being drawn as zero.
 */
final readonly class LineChart
{
    /**
     * @param  list<string>  $paths  SVG path data, one per unbroken run of values.
     * @param  list<array{x: float, y: float, date: string, value: float}>  $points
     * @param  list<array{y: float, value: float}>  $ticks  Horizontal grid lines, bottom to top.
     */
    private function __construct(
        public float $width,
        public float $height,
        public array $paths,
        public array $points,
        public array $ticks,
    ) {}

    /**
     * @param  array<string, int|float|null>  $series  Value by date (Y-m-d), oldest first.
     */
    public static function make(array $series, float $width, float $height): self
    {
        $values = array_filter($series, fn (int|float|null $value): bool => $value !== null);
        $top = self::niceMax($values === [] ? 0.0 : (float) max($values));
        $step = count($series) > 1 ? $width / (count($series) - 1) : 0.0;

        $paths = [];
        $points = [];
        $run = '';
        $index = 0;

        foreach ($series as $date => $value) {
            if ($value === null) {
                if ($run !== '') {
                    $paths[] = $run;
                    $run = '';
                }
                $index++;

                continue;
            }

            $x = count($series) > 1 ? round($index * $step, 2) : $width / 2;
            $y = round($height - ((float) $value / $top) * $height, 2);
            $run .= ($run === '' ? 'M' : ' L').$x.' '.$y;
            $points[] = ['x' => $x, 'y' => $y, 'date' => (string) $date, 'value' => (float) $value];
            $index++;
        }

        if ($run !== '') {
            $paths[] = $run;
        }

        $ticks = array_map(fn (float $value): array => ['y' => round($height - ($value / $top) * $height, 2), 'value' => $value], [0.0, $top / 2, $top]);

        return new self($width, $height, $paths, $points, $ticks);
    }

    /**
     * The top of the scale: a round number at or above the largest value
     * (1, 2, 2.5 or 5 times a power of ten), never below 1.
     */
    public static function niceMax(float $max): float
    {
        if ($max <= 1) {
            return 1.0;
        }

        $magnitude = 10 ** floor(log10($max));

        foreach ([1, 2, 2.5, 5, 10] as $factor) {
            if ($factor * $magnitude >= $max) {
                return $factor * $magnitude;
            }
        }

        return 10 * $magnitude;
    }
}
