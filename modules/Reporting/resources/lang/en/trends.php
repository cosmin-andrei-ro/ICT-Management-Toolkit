<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'period' => 'Period',
    'days' => '{1} :count day|[2,*] :count days',
    'over' => '{1} Over the last day|[2,*] Over the last :count days',
    'now' => 'Now',
    'summary' => [
        'first' => 'At the start of the period',
        'lowest' => 'Lowest',
        'highest' => 'Highest',
        'average' => 'Average',
    ],
    'no_values' => 'No values recorded yet for this period. Values are recorded once a day.',
    'table' => 'Show the values as a table',
    'date' => 'Date',
    'value' => 'Value',
    'daily' => 'Values recorded once a day. Days without a value leave a gap in the line.',
    'open_list' => 'Open the list behind this figure',
    'trend' => 'Trend',
    'trend_of' => 'Trend over the last 30 days: :metric',
];
