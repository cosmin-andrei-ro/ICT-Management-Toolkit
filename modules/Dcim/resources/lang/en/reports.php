<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'metrics' => [
        'group' => 'Data centre',
        'rack_space_used' => 'Rack space used',
        'power_over' => 'Power sources over capacity',
        'power_high' => 'Power sources under high load',
        'single_fed' => 'Equipment with a single power supply',
        'subnets_full' => 'IPv4 subnets 90% full or more',
    ],
    'export' => [
        'button' => 'Export to Excel',
        'of' => 'Data centre workbook of :date',
        'racks' => 'Racks',
        'equipment' => 'Equipment',
        'units_used' => 'Units used',
        'power_planned' => 'Power draw (W)',
        'units' => 'Units',
        'asset_tag' => 'Asset tag',
        'asset_name' => 'Name',
        'model' => 'Make and model',
        'serial' => 'Serial number',
        'supplies' => 'Power supplies',
        'watts' => 'W',
        'length' => 'Length (m)',
        'a_equipment' => 'End A equipment',
        'a_port' => 'End A port',
        'b_equipment' => 'End B equipment',
        'b_port' => 'End B port',
        'load_normal' => 'Load (W)',
        'load_worst' => 'Load if the other side fails (W)',
        'unknown_draw' => 'Devices without power draw',
        'subnet' => 'Subnet',
    ],
    'sheet' => [
        'button' => 'Print the rack',
        'printed' => 'Printed :date',
    ],
];
