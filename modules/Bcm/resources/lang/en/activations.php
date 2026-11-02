<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'open' => 'Under way',
    'closed' => 'Closed',
    'fields' => [
        'activated_at' => 'Activated at',
        'activated_by' => 'Recorded by',
        'reason' => 'What happened',
        'restored_at' => 'Service back at',
        'closed_by' => 'Closed by',
        'summary' => 'How it went',
        'notify_team' => 'Email the crisis team members who have an account, and the plan owner and deputy, a link to the plan.',
    ],
    'ui' => [
        'new' => 'Activate the plan',
        'new_help' => 'Record that the plan was activated for a real disruption. The record keeps the version in force and the strictest RTO of this moment. Raise the incident in the service desk as usual.',
        'record' => 'Record the activation',
        'title' => 'Activation of :plan',
        'list' => 'Activations',
        'details' => 'Details',
        'recovered_in' => 'The service was back :value after the activation.',
        'save' => 'Save',
        'close' => 'Close the activation',
        'close_confirm' => 'Close the activation? It can no longer be changed.',
        'close_help' => 'Close it once the service is back and the lessons are written down; it is then kept as a record.',
        'print' => 'Print the record',
    ],
    'mail' => [
        'subject' => 'Plan activated: :plan',
        'line' => ':name activated the plan ":plan" on :date. Follow the plan and your role in it.',
        'plan' => 'Open the plan',
        'record' => 'The activation is followed here: :url',
    ],
    'errors' => [
        'invalid' => 'The plan has no version in force, or the time is in the future.',
        'restored' => 'The service cannot be back before the activation or in the future, and an activation is closed only once the service is back.',
    ],
];
