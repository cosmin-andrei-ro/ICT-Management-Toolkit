<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'menu' => 'Continuity coverage',
    'title' => 'Continuity coverage',
    'intro' => 'Whether every assessed business process has a plan in force that brings it back in time, and which plans need approval, a review or an exercise.',
    'figures' => [
        'uncovered' => 'Assessed processes without a plan',
        'not_in_force' => 'Plans not approved',
        'review_overdue' => 'Reviews overdue',
        'exercise_overdue' => 'Exercises overdue',
        'awaiting_approval' => 'Waiting for approval',
        'open_findings' => 'Findings not handed over',
    ],
    'plans_heading' => 'Plans',
    'processes_heading' => 'Assessed business processes',
    'next_exercise' => 'Next exercise due',
    'recovery' => 'Recovery / RTO',
    'recovery_help' => 'Recovery: activation and recovery steps of the version in force, as estimated; RTO: the strictest of the processes it covers. Red when the plan would not bring them back in time.',
    'not_in_force' => 'Not in force',
    'awaiting' => 'Waiting for approval',
    'process' => 'Process',
    'rto' => 'RTO',
    'plans' => 'Plans in force',
    'within_rto' => 'Back within RTO',
    'tested' => 'Exercised',
    'tier' => 'tier :tier',
    'no_plan' => 'No plan',
    'yes' => 'Yes',
    'no' => 'No',
    'gaps_only' => 'Only what needs attention',
    'no_processes' => 'No assessed process.',
];
