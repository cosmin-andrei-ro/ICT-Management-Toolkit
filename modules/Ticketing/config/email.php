<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [

    // Ports mailboxes may use. 993 = IMAP over TLS, 143 = IMAP (with STARTTLS).
    'allowed_ports' => array_map(intval(...), explode(',', (string) env('TICKETING_MAIL_ALLOWED_PORTS', '993,143'))),

    // Unencrypted IMAP sends the password in clear text: development only.
    'allow_unencrypted' => (bool) env('TICKETING_MAIL_ALLOW_UNENCRYPTED', false),

    // Keep on; trust an internal CA by adding it to the container's CA bundle.
    'verify_certificates' => (bool) env('TICKETING_MAIL_VERIFY_CERTIFICATES', true),

    // Messages read per mailbox and run (every minute).
    'messages_per_run' => (int) env('TICKETING_MAIL_MESSAGES_PER_RUN', 50),

    // Larger messages are ignored (logged).
    'max_message_kilobytes' => (int) env('TICKETING_MAIL_MAX_MESSAGE_KB', 25600),

    // Tickets and replies accepted per sender and hour, against mail loops and floods.
    'max_per_sender_per_hour' => (int) env('TICKETING_MAIL_MAX_PER_SENDER_PER_HOUR', 20),

    // Days the log of read messages is kept (it holds email addresses and subjects).
    'retention_days' => (int) env('TICKETING_MAIL_RETENTION_DAYS', 90),

];
