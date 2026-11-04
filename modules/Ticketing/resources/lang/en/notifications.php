<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'reply_by_email' => 'You can reply to this email: your reply is added to the ticket.',
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
        'subject' => '[:number] New reply from IT support',
        'line' => 'IT support replied on ticket :number.',
    ],
    'requester_replied' => [
        'subject' => '[:number] The requester replied',
        'line' => 'The requester replied on ticket :number.',
    ],
    'sla_response_warning' => [
        'subject' => '[:number] First response due soon',
        'line' => 'Ticket :number has used three quarters of its first-response time.',
    ],
    'sla_resolution_warning' => [
        'subject' => '[:number] Resolution due soon',
        'line' => 'Ticket :number has used three quarters of its resolution time.',
    ],
    'sla_response_breached' => [
        'subject' => '[:number] First response overdue',
        'line' => 'Ticket :number missed its first-response target.',
    ],
    'sla_resolution_breached' => [
        'subject' => '[:number] Resolution overdue',
        'line' => 'Ticket :number missed its resolution target.',
    ],
    'change_submitted' => [
        'subject' => '[:number] Change waiting for your approval',
        'line' => 'Change :number was submitted to your board and needs your decision.',
    ],
    'change_approved' => [
        'subject' => '[:number] Change authorized',
        'line' => 'Every required board member approved change :number. You can implement it in its window.',
    ],
    'change_rejected' => [
        'subject' => '[:number] Change rejected',
        'line' => 'Change :number was rejected. Read the comment, adjust the plan and submit it again.',
    ],
];
