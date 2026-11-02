<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Sheets;

/**
 * Lays out the network diagram (4.2) from the firewall rules (4.3): every
 * source and destination is a box, every rule an arrow labelled with its port
 * and protocol. Boxes are placed in columns by how far they are from a
 * starting point (a source nothing points to), so traffic reads left to right.
 */
final class NetworkDiagram
{
    public const int BOX_WIDTH = 170;

    public const int BOX_HEIGHT = 40;

    private const int COLUMN_GAP = 90;

    private const int ROW_GAP = 30;

    private const int MARGIN = 20;

    /**
     * @param  list<array<mixed>>  $rules  Rows of 4.3.
     * @return array{width: int, height: int, nodes: array<string, array{name: string, x: int, y: int}>, edges: list<array{from: string, to: string, port: string, protocol: string}>}
     */
    public static function layout(array $rules): array
    {
        $names = [];
        $edges = [];

        foreach ($rules as $rule) {
            $source = self::text($rule['source'] ?? null);
            $destination = self::text($rule['destination'] ?? null);

            if ($source === '' || $destination === '') {
                continue;
            }

            $from = self::key($source);
            $to = self::key($destination);
            $names[$from] ??= $source;
            $names[$to] ??= $destination;
            $edges[] = ['from' => $from, 'to' => $to, 'port' => self::text($rule['port'] ?? null), 'protocol' => self::text($rule['protocol'] ?? null)];
        }

        $columns = self::columns(array_keys($names), $edges);
        $nodes = [];
        $rowsPerColumn = [];

        foreach ($columns as $key => $column) {
            $row = $rowsPerColumn[$column] ?? 0;
            $rowsPerColumn[$column] = $row + 1;
            $nodes[$key] = [
                'name' => $names[$key],
                'x' => self::MARGIN + $column * (self::BOX_WIDTH + self::COLUMN_GAP),
                'y' => self::MARGIN + $row * (self::BOX_HEIGHT + self::ROW_GAP),
            ];
        }

        $columnCount = $columns === [] ? 0 : max($columns) + 1;
        $rowCount = $rowsPerColumn === [] ? 0 : max($rowsPerColumn);

        return [
            'width' => max(300, 2 * self::MARGIN + $columnCount * self::BOX_WIDTH + max(0, $columnCount - 1) * self::COLUMN_GAP),
            'height' => max(80, 2 * self::MARGIN + $rowCount * self::BOX_HEIGHT + max(0, $rowCount - 1) * self::ROW_GAP),
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /**
     * The longest path from a starting point, cycles broken by visiting each
     * box once from each start.
     *
     * @param  list<string>  $keys
     * @param  list<array{from: string, to: string, port: string, protocol: string}>  $edges
     * @return array<string, int>
     */
    private static function columns(array $keys, array $edges): array
    {
        $outgoing = [];
        $incoming = [];

        foreach ($edges as $edge) {
            if ($edge['from'] !== $edge['to']) {
                $outgoing[$edge['from']][$edge['to']] = true;
                $incoming[$edge['to']] = true;
            }
        }

        $starts = array_values(array_filter($keys, fn (string $key): bool => ! isset($incoming[$key])));

        if ($starts === [] && $keys !== []) {
            $starts = [$keys[0]];
        }

        $column = [];

        foreach ($starts as $start) {
            $column[$start] = max($column[$start] ?? 0, 0);
            $queue = [[$start, 0]];
            $seen = [$start => true];

            while ($queue !== []) {
                [$key, $depth] = array_shift($queue);

                foreach (array_keys($outgoing[$key] ?? []) as $next) {
                    if (isset($seen[$next])) {
                        continue;
                    }

                    $seen[$next] = true;
                    $column[$next] = max($column[$next] ?? 0, $depth + 1);
                    $queue[] = [$next, $depth + 1];
                }
            }
        }

        // Boxes reached only through a cycle not walked from any start.
        foreach ($keys as $key) {
            $column[$key] ??= 0;
        }

        return $column;
    }

    private static function key(string $name): string
    {
        return mb_strtolower($name);
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
