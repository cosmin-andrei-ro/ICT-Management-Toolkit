<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'source' => 'Continuity plans',
    'no_plan' => [
        'title' => 'Prolonged disruption of :process: no plan in force',
        'description' => 'The process :process is assessed in the BIA but no continuity or recovery plan in force covers it.',
    ],
    'too_slow' => [
        'title' => ':plan would not bring the service back in time',
        'description' => 'The activation and recovery steps of :plan take longer than the strictest RTO of the processes it covers.',
    ],
];
