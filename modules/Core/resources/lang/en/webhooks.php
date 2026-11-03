<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'navigation_group' => 'Integrations',
    'title' => 'Webhooks',
    'model' => 'Webhook',
    'deliveries' => 'Webhook deliveries',
    'test' => 'Send a test',
    'test_title' => 'Test event',
    'test_sent' => 'A test event is on its way. See the deliveries for the answer.',
    'rotate' => 'New secret',
    'rotate_help' => 'The receiver must use the new secret to check signatures from now on.',
    'retry' => 'Send again',
    'retried' => 'The delivery is on its way again.',
    'secret_title' => 'Signing secret',
    'secret_body' => 'Give this secret to the receiver to check the X-Webhook-Signature header. It is shown only now: :secret',
    'fields' => [
        'name' => 'Name',
        'url' => 'Address',
        'url_help' => 'HTTPS, on a public network unless this installation allows private ones.',
        'events' => 'Events',
        'active' => 'Active',
        'when' => 'When',
        'event' => 'Event',
        'status' => 'Status',
        'attempts' => 'Attempts',
        'response' => 'Answer',
        'error' => 'Error',
    ],
    'statuses' => [
        'pending' => 'Pending',
        'delivered' => 'Delivered',
        'failed' => 'Failed',
    ],
    'errors' => [
        'url' => 'This address is not allowed: use HTTPS, and a public address unless this installation allows private ones.',
    ],
];
