<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'menu' => 'Recovery gaps',
    'title' => 'Recovery gaps',
    'intro' => 'For each IT system that approved business processes use, directly or through the systems they depend on: the strictest recovery the business requires, against what IT declared it can deliver on the system\'s CMDB page.',
    'only_problems' => 'Only gaps and undeclared capabilities',
    'cmdb_off' => 'The CMDB is not enabled: there is nothing to compare.',
    'empty' => 'No gaps. Link IT systems to processes in their assessments to see them here.',
    'counts' => [
        'gap' => 'Gaps',
        'unknown' => 'Not declared',
        'met' => 'Met',
    ],
    'item' => 'IT system',
    'required' => 'Required',
    'capability' => 'IT can deliver',
    'processes' => 'Required by',
    'via' => 'through a dependency',
    'criticality' => 'Criticality',
    'suggested' => 'BIA suggests :criticality',
    'align' => 'Apply',
    'statuses' => [
        'gap' => 'Gap',
        'unknown' => 'Not declared',
        'met' => 'Met',
    ],
    'rto' => 'back within :value',
    'rpo' => 'data loss at most :value',
    'invalid' => 'The criticality could not be changed.',
];
