<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'options' => [
        'mitigate' => 'Mitigate',
        'transfer' => 'Transfer',
        'avoid' => 'Avoid',
        'accept' => 'Accept',
    ],
    'option_help' => [
        'mitigate' => 'Add or improve controls to reduce the likelihood or the impact.',
        'transfer' => 'Share the risk with someone else, e.g. insurance or a supplier\'s contract.',
        'avoid' => 'Stop the activity that causes the risk.',
        'accept' => 'Keep the risk as it is, knowingly.',
    ],
    'action_statuses' => [
        'planned' => 'Planned',
        'in_progress' => 'In progress',
        'done' => 'Done',
        'cancelled' => 'Cancelled',
    ],
    'acceptance_statuses' => [
        'pending' => 'Waiting for approval',
        'approved' => 'Accepted',
        'rejected' => 'Rejected',
        'withdrawn' => 'Withdrawn',
        'lapsed' => 'Lapsed (residual risk changed)',
    ],
    'fields' => [
        'option' => 'Treatment option',
        'plan' => 'Treatment plan',
        'residual' => 'Residual risk',
        'residual_likelihood' => 'Residual likelihood',
        'residual_impact' => 'Residual impact',
        'action' => 'Action',
        'action_owner' => 'Owner',
        'due_on' => 'Due on',
        'justification' => 'Why the residual risk is acceptable',
        'decision_comment' => 'Comment (required to reject)',
    ],
    'examples' => [
        'plan' => 'e.g. Add offline immutable backups and test a restore every quarter; enforce MFA on all remote access',
        'action' => 'e.g. Enable MFA for all VPN users',
        'justification' => 'e.g. Remaining exposure limited to a few hours of data; further controls would cost more than the expected loss',
    ],
    'ui' => [
        'treatment' => 'Treatment',
        'residual_help' => 'The residual risk is the level expected once the treatment is in place. Changing it voids an acceptance given.',
        'save_treatment' => 'Save the treatment',
        'actions' => 'Treatment actions',
        'no_actions' => 'No action yet.',
        'add_action' => 'Add the action',
        'due' => 'due :date',
        'no_due' => 'no due date',
        'start' => 'Start',
        'done' => 'Done',
        'cancel' => 'Cancel',
        'raise_ticket' => 'Track in the service desk',
        'acceptance' => 'Acceptance of the residual risk',
        'residual_first' => 'Assess the residual risk first.',
        'accept_at_once' => 'Below :threshold, the risk owner accepts the residual risk.',
        'needs_approval' => 'From :threshold, a risk manager other than the owner must approve the acceptance.',
        'request_acceptance' => 'Ask for acceptance',
        'accept' => 'Accept the residual risk',
        'acceptance_pending' => 'Acceptance waiting for approval',
        'pending_line' => ':name asks to accept a residual risk of :score.',
        'approve' => 'Approve',
        'reject' => 'Reject',
        'accepted_until' => 'Accepted until :date',
        'score' => 'score :score',
        'until' => 'until :date',
        'history' => 'Score history',
        'no_history' => 'Not scored yet.',
        'date' => 'Date',
        'by' => 'By',
    ],
    'ticket' => [
        'title' => 'Risk treatment :risk: :action',
        'description' => "Action of the treatment of risk :risk.\n\nAction: :action\nDue on: :due\n\nRisk: :url",
    ],
    'mail' => [
        'acceptance_requested' => [
            'subject' => 'Risk acceptance to approve: :risk',
            'line' => 'The owner of :risk asks to accept a residual risk of :detail. Approve or reject it.',
        ],
        'acceptance_approved' => [
            'subject' => 'Risk acceptance approved: :risk',
            'line' => 'The acceptance of the residual risk of :risk was approved.',
        ],
        'acceptance_rejected' => [
            'subject' => 'Risk acceptance rejected: :risk',
            'line' => 'The acceptance of the residual risk of :risk was rejected: :detail',
        ],
        'review_due' => [
            'subject' => 'Risk due for reassessment: :risk',
            'line' => ':risk is due for reassessment on :detail. Check its scores, controls and treatment, then save the assessment.',
        ],
        'open' => 'Open the risk',
    ],
    'errors' => [
        'cannot_request' => 'Assess the residual risk first, and check no acceptance is already waiting.',
        'cannot_decide' => 'You cannot decide this acceptance: the risk owner and the person who asked never approve it.',
    ],
];
