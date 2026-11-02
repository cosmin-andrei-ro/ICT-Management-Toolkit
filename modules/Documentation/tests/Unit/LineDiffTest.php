<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Documentation\Application\Diff\LineDiff;

/**
 * @param  list<array{type: string, line: string}>  $diff
 */
function rebuilt(array $diff, string $side): string
{
    return implode("\n", array_map(fn (array $row): string => $row['line'], array_values(array_filter($diff, fn (array $row): bool => $row['type'] === 'same' || $row['type'] === $side))));
}

it('shows the lines added, removed and kept between two versions', function (): void {
    $diff = LineDiff::between("# Backup\nRun nightly\nKeep 7 days", "# Backup\nRun nightly at 02:00\nKeep 7 days\nTest monthly");

    expect($diff)->toBe([
        ['type' => 'same', 'line' => '# Backup'],
        ['type' => 'removed', 'line' => 'Run nightly'],
        ['type' => 'added', 'line' => 'Run nightly at 02:00'],
        ['type' => 'same', 'line' => 'Keep 7 days'],
        ['type' => 'added', 'line' => 'Test monthly'],
    ]);
});

it('always rebuilds both texts from the script', function (string $old, string $new): void {
    $diff = LineDiff::between($old, $new);

    expect(rebuilt($diff, 'removed'))->toBe($old)->and(rebuilt($diff, 'added'))->toBe($new);
})->with([
    'empty to text' => ['', "a\nb"],
    'text to empty' => ["a\nb", ''],
    'identical' => ["a\nb\nc", "a\nb\nc"],
    'reordered' => ["a\nb\nc\nd", "d\nc\nb\na"],
    'repeated lines' => ["x\nx\ny\nx", "y\nx\nx\nx\ny"],
    'random' => [implode("\n", range(1, 60)), implode("\n", array_merge(range(1, 20), ['new'], range(25, 70)))],
]);
