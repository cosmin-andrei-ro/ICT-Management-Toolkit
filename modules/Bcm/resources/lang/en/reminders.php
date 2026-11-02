<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'mail' => [
        'review' => [
            'subject' => 'Plan due for review: :plan',
            'line' => 'The plan ":plan" is due for review on :date. Start a review so it stays accurate.',
        ],
        'exercise' => [
            'subject' => 'Plan due for an exercise: :plan',
            'line' => 'The plan ":plan" should be exercised by :date. Plan an exercise to check that it works.',
        ],
        'open' => 'Open the plan',
        'digest' => [
            'subject' => 'Continuity plans: what needs attention',
            'uncovered' => '{1} :count assessed process has no plan in force.|[2,*] :count assessed processes have no plan in force.',
            'not_in_force' => '{1} :count plan has no approved version.|[2,*] :count plans have no approved version.',
            'review_overdue' => '{1} :count plan is overdue for review.|[2,*] :count plans are overdue for review.',
            'exercise_overdue' => '{1} :count plan is overdue for an exercise.|[2,*] :count plans are overdue for an exercise.',
            'awaiting_approval' => '{1} :count plan version waits for approval.|[2,*] :count plan versions wait for approval.',
            'open_findings' => '{1} :count exercise finding was not handed to the service desk.|[2,*] :count exercise findings were not handed to the service desk.',
            'open_activations' => '{1} :count plan activation is still open.|[2,*] :count plan activations are still open.',
            'action' => 'Open the coverage report',
        ],
    ],
];
