<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'open_ticket' => 'Open the ticket',
    'created' => [
        'subject' => '[:number] We received your request',
        'line' => 'Your ticket :number has been registered. We will get back to you.',
    ],
    'assigned' => [
        'subject' => '[:number] Assigned to you',
        'line' => 'Ticket :number has been assigned to you.',
    ],
    'resolved' => [
        'subject' => '[:number] Resolved',
        'line' => 'Ticket :number has been resolved. If the problem persists, you can reopen it.',
    ],
    'desk_replied' => [
        'subject' => '[:number] New reply from the service desk',
        'line' => 'The service desk replied on ticket :number.',
    ],
    'requester_replied' => [
        'subject' => '[:number] The requester replied',
        'line' => 'The requester replied on ticket :number.',
    ],
];
