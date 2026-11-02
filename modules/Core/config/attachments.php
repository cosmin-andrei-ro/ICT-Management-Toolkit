<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

/*
| Uploads (config('core.attachments')): the MIME type is detected from the file
| content, never taken from the client, and must be in this list.
*/

return [

    'disk' => (string) env('ATTACHMENTS_DISK', 'attachments'),

    'max_kilobytes' => (int) env('ATTACHMENTS_MAX_KB', 20 * 1024),

    'allowed_mime_types' => [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
        'text/plain',
        'text/csv',
        'application/zip',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'message/rfc822',
        // Diagram sources (.drawio) are XML; always downloaded, never rendered.
        'text/xml',
        'application/xml',
    ],

];
