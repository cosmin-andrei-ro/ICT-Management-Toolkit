<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'metrics' => [
        'response' => 'First response',
        'resolution' => 'Resolution',
    ],
    'title' => 'Service level',
    'paused' => 'Paused',
    'due_in' => 'Due in :time',
    'overdue_since' => 'Overdue :time',
    'target' => 'Target',
    'used' => 'Used',
    'due' => 'Due',
    'minutes' => ':count min',
    'hours' => ':count h',
    'filter' => [
        'label' => 'SLA',
        'any' => 'Any',
        'at_risk' => 'At risk or overdue',
        'breached' => 'Overdue',
    ],
    'outcomes' => [
        'met' => 'Met',
        'breached' => 'Missed',
        'cancelled' => 'Not applicable',
    ],
];
