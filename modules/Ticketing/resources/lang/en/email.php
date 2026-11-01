<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'no_subject' => '(no subject)',
    'no_text' => '(no text)',
    'attachments_only' => '(attachments sent by email)',
    'attachments_refused' => 'Attachments not accepted (type or size not allowed): :files',
    'follow_up' => 'Follow-up of :number, which is closed.',
    'encryption' => [
        'ssl' => 'SSL/TLS (port 993)',
        'starttls' => 'STARTTLS (port 143)',
        'none' => 'None (development only)',
    ],
    'outcomes' => [
        'created' => 'Ticket created',
        'commented' => 'Added to ticket',
        'ignored_unknown_sender' => 'Ignored: unknown sender',
        'ignored_automatic' => 'Ignored: automatic message',
        'ignored_spoofed' => 'Ignored: sender failed verification',
        'ignored_rate_limited' => 'Ignored: too many messages from sender',
        'ignored_too_large' => 'Ignored: message too large',
        'failed' => 'Failed',
    ],
    'reasons' => [
        'connection' => 'Cannot connect to the server.',
        'authentication' => 'Sign-in refused: check the username and password.',
        'folder' => 'Folder not found.',
        'host_not_allowed' => 'This server address is not allowed.',
        'port_not_allowed' => 'This port is not allowed.',
        'unencrypted' => 'Unencrypted connections are not allowed.',
        'host_not_found' => 'Server name not found.',
    ],
    'admin' => [
        'label' => 'Mailbox',
        'plural_label' => 'Mailboxes',
        'address' => 'Email address',
        'address_help' => 'The support address people write to. Ticket emails use it as the reply address.',
        'host' => 'IMAP server',
        'port' => 'Port',
        'port_help' => 'Allowed ports: :ports.',
        'encryption' => 'Encryption',
        'username' => 'Username',
        'password' => 'Password',
        'password_keep' => 'Leave empty to keep the current password. It is stored encrypted and never shown.',
        'folder' => 'Folder to read',
        'processed_folder' => 'Move processed messages to',
        'processed_folder_help' => 'An existing folder, e.g. INBOX/Processed. Empty: processed messages are only marked as read.',
        'ticket_type' => 'New tickets are',
        'queue' => 'Queue',
        'queue_help' => 'Empty: the default queue.',
        'last_checked' => 'Last checked',
        'status' => 'Status',
        'ok' => 'OK',
        'test' => 'Test connection',
        'test_ok' => 'Connected and signed in; the folders exist.',
        'test_failed' => 'Connection test failed',
        'test_throttled' => 'Too many tests. Wait a minute.',
    ],
    'log' => [
        'label' => 'Incoming email',
        'plural_label' => 'Incoming email',
        'processed_at' => 'Read at',
        'from' => 'From',
        'subject' => 'Subject',
        'outcome' => 'Outcome',
        'ticket' => 'Ticket',
    ],
];
