<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'details' => "Opened by monitoring.\nSource: :source\nAlert key: :key\nSeverity: :severity\nAffected item: :item",
    'unknown_source' => 'not given',
    'no_item' => 'not given',
    'cleared' => 'The monitoring alert cleared after :count occurrence(s).',
    'panel' => [
        'title' => 'Monitoring alert',
        'status' => 'Status',
        'firing' => 'Firing',
        'resolved' => 'Cleared',
        'occurrences' => 'Occurrences',
        'first_seen' => 'First seen',
        'last_seen' => 'Last seen',
        'source' => 'Source',
        'key' => 'Alert key',
    ],
];
