<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Application\Diff;

/**
 * What changed between two versions of a text, line by line (Myers' shortest
 * edit script). Very different texts are shown as all removed, then all
 * added, rather than spending time on a minimal script.
 */
final class LineDiff
{
    /** Edit distance beyond which the texts are treated as rewritten. */
    private const int MAX_EDITS = 2000;

    /**
     * @return list<array{type: 'same'|'added'|'removed', line: string}>
     */
    public static function between(string $old, string $new): array
    {
        $before = $old === '' ? [] : explode("\n", $old);
        $after = $new === '' ? [] : explode("\n", $new);
        $trace = self::trace($before, $after);

        if ($trace === null) {
            return [
                ...array_map(fn (string $line): array => ['type' => 'removed', 'line' => $line], $before),
                ...array_map(fn (string $line): array => ['type' => 'added', 'line' => $line], $after),
            ];
        }

        return self::backtrack($trace, $before, $after);
    }

    /**
     * @param  list<string>  $before
     * @param  list<string>  $after
     * @return ?list<array<int, int>> The furthest reach on each diagonal, before each edit count.
     */
    private static function trace(array $before, array $after): ?array
    {
        $n = count($before);
        $m = count($after);
        $reach = [1 => 0];
        $trace = [];

        for ($edits = 0; $edits <= min($n + $m, self::MAX_EDITS); $edits++) {
            $trace[] = $reach;

            for ($k = -$edits; $k <= $edits; $k += 2) {
                $x = $k === -$edits || ($k !== $edits && ($reach[$k - 1] ?? -1) < ($reach[$k + 1] ?? -1))
                    ? ($reach[$k + 1] ?? 0)
                    : ($reach[$k - 1] ?? 0) + 1;
                $y = $x - $k;

                while ($x < $n && $y < $m && $before[$x] === $after[$y]) {
                    $x++;
                    $y++;
                }

                $reach[$k] = $x;

                if ($x >= $n && $y >= $m) {
                    return $trace;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<array<int, int>>  $trace
     * @param  list<string>  $before
     * @param  list<string>  $after
     * @return list<array{type: 'same'|'added'|'removed', line: string}>
     */
    private static function backtrack(array $trace, array $before, array $after): array
    {
        $x = count($before);
        $y = count($after);
        $operations = [];

        for ($edits = count($trace) - 1; $edits >= 0; $edits--) {
            $reach = $trace[$edits];
            $k = $x - $y;
            $previousK = $k === -$edits || ($k !== $edits && ($reach[$k - 1] ?? -1) < ($reach[$k + 1] ?? -1)) ? $k + 1 : $k - 1;
            $previousX = $reach[$previousK] ?? 0;
            $previousY = $previousX - $previousK;

            while ($x > $previousX && $y > $previousY) {
                $operations[] = ['type' => 'same', 'line' => $before[$x - 1]];
                $x--;
                $y--;
            }

            if ($edits > 0) {
                if ($x === $previousX) {
                    $operations[] = ['type' => 'added', 'line' => $after[$y - 1]];
                    $y--;
                } else {
                    $operations[] = ['type' => 'removed', 'line' => $before[$x - 1]];
                    $x--;
                }
            }
        }

        return array_reverse($operations);
    }
}
