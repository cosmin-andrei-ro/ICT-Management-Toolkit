<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'title' => 'Data retention',
    'intro' => 'How long the organization keeps its data (GDPR storage limitation). Every night, what is older is deleted or anonymized, as each line says. Leave a period empty to keep that data forever.',
    'years' => 'Years',
    'default' => 'Default: :years years.',
    'save' => 'Save',
    'saved' => 'Retention periods saved.',
    'policies' => [
        'audit_log' => 'Audit log',
        'audit_log_help' => 'Older entries are deleted. Sign-ins outside any organization are kept as long as the organization that keeps its log longest.',
        'webhook_deliveries' => 'Webhook deliveries',
        'webhook_deliveries_help' => 'Sent and failed deliveries older than this are deleted, with the record titles their payload carries.',
    ],
];
