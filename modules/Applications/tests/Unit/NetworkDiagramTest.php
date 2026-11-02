<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Applications\Application\Sheets\NetworkDiagram;

it('places each box after the boxes sending traffic to it', function (): void {
    $layout = NetworkDiagram::layout([
        ['source' => 'Users (VLAN 100)', 'destination' => 'SD-LB-VIP', 'port' => '443', 'protocol' => 'https'],
        ['source' => 'SD-LB-VIP', 'destination' => 'SDAPP01 / 02', 'port' => '8443', 'protocol' => 'https'],
        ['source' => 'SDAPP01 / 02', 'destination' => 'SDDB01', 'port' => '5432', 'protocol' => 'database'],
        ['source' => 'SDWRK01', 'destination' => 'sddb01', 'port' => '5432', 'protocol' => 'database'],
        ['source' => '', 'destination' => 'ignored', 'port' => '1'],
    ]);

    $x = array_map(fn (array $node): int => $node['x'], $layout['nodes']);
    expect(array_column($layout['nodes'], 'name'))->toBe(['Users (VLAN 100)', 'SD-LB-VIP', 'SDAPP01 / 02', 'SDDB01', 'SDWRK01'])
        ->and($x['users (vlan 100)'])->toBeLessThan($x['sd-lb-vip'])
        ->and($x['sd-lb-vip'])->toBeLessThan($x['sdapp01 / 02'])
        ->and($x['sdapp01 / 02'])->toBeLessThan($x['sddb01'])
        ->and($x['sdwrk01'])->toBe($x['users (vlan 100)'])
        ->and($layout['edges'])->toHaveCount(4)
        ->and($layout['width'])->toBeGreaterThan(4 * NetworkDiagram::BOX_WIDTH);
});

it('survives loops and an empty table', function (): void {
    $layout = NetworkDiagram::layout([
        ['source' => 'A', 'destination' => 'B', 'port' => '1'],
        ['source' => 'B', 'destination' => 'A', 'port' => '2'],
    ]);

    expect($layout['nodes'])->toHaveCount(2)
        ->and(NetworkDiagram::layout([])['nodes'])->toBe([]);
});
