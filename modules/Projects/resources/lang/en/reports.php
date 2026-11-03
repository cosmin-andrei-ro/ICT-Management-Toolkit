<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'print' => 'Print the status report',
    'metrics' => [
        'group' => 'Projects',
        'red' => 'Open projects reported red',
        'over_budget' => 'Open projects over budget',
        'overdue_tasks' => 'Overdue project tasks',
        'gates_waiting' => 'Stage gates waiting for a sponsor',
    ],
    'export' => [
        'button' => 'Export to Excel',
        'of' => 'Project portfolio of :date',
        'projects' => 'Projects',
        'costs' => 'Costs',
        'code' => 'Code',
        'latest_rag' => 'Latest status',
        'reported_on' => 'Reported on',
        'tasks' => 'Tasks',
        'tasks_done' => 'Tasks done',
        'tasks_overdue' => 'Tasks overdue',
        'linked' => 'Linked record',
        'delivered_on' => 'Delivered on',
    ],
    'sheet' => [
        'kicker' => 'Project status report',
        'printed' => 'Printed :date',
        'planned' => 'Planned :start → :end',
        'started' => 'Started :date',
        'completed' => 'Completed :date',
        'status' => 'Latest status',
        'delivered' => 'Delivered :date',
        'overdue' => '{1} One task is overdue:|[2,*] :count tasks are overdue:',
        'decisions' => 'Sponsor decisions',
    ],
];
