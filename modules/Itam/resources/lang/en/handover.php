<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'title' => 'Equipment handover protocol',
    'reference' => 'Reference :reference',
    'back' => '← Back',
    'print' => 'Print or save as PDF',
    'protocol' => 'Handover protocol',
    'handed_by' => 'Handed over by',
    'received_by' => 'Received by',
    'equipment' => 'Equipment',
    'make_model' => 'Make and model',
    'statement' => 'The person receiving the equipment confirms that it was received in working order, will be used for work and looked after, and will be returned to IT on request or on leaving the organization. Loss or theft is reported to IT at once.',
    'acknowledged' => 'Receipt confirmed electronically by the recipient on :date (from :ip).',
    'signature' => 'Signature: :name',
    'return_title' => 'Return',
    'returned' => 'Returned on :date, received by :name.',
    'confirm' => 'I received it',
    'confirmed' => 'Receipt confirmed',
    'to_confirm' => 'Please confirm you received this',
    'mail' => [
        'subject' => 'You received :name (:tag)',
        'line' => 'IT has handed :name (asset tag :tag) to you. Please confirm in the portal that you received it.',
        'action' => 'Confirm receipt',
        'not_you' => 'If you did not receive it, tell the service desk.',
    ],
];
