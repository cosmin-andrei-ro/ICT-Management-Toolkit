<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'mail' => [
        'due' => [
            'subject' => 'BIA review due on :date: :process',
            'line' => 'The impact analysis of ":process" is due for review on :date. Open the process and click "Start a review": it starts from the version in force, so you only change what changed.',
            'action' => 'Open the process',
        ],
        'digest' => [
            'subject' => 'Business impact analysis: what needs attention',
            'overdue' => 'Reviews overdue: :count',
            'never_assessed' => 'Processes never assessed: :count',
            'awaiting_approval' => 'Assessments waiting for your approval: :count',
            'action' => 'Open the processes',
        ],
    ],
];
