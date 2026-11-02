<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'title' => 'Suggested risks',
    'statuses' => [
        'open' => 'To review',
        'converted' => 'Registered',
        'dismissed' => 'Dismissed',
        'resolved' => 'No longer applies',
    ],
    'ui' => [
        'intro' => 'What the other modules know that suggests a risk: applications, continuity, the service desk, assets, suppliers and compliance. Nothing becomes a risk until you register it.',
        'link' => 'Suggested risks (:count)',
        'refresh' => 'Check now',
        'refreshed' => ':opened new suggestion(s); :resolved no longer apply.',
        'source' => 'Source',
        'seen' => 'seen since :first, last on :last',
        'evidence' => 'See where it comes from',
        'scores' => 'Suggested likelihood :likelihood, impact :impact',
        'suggested_owner' => 'Suggested: :name',
        'convert' => 'Register the risk',
        'reason' => 'Or dismiss it, saying why',
        'reason_example' => 'e.g. Covered by risk R-0004',
        'dismiss' => 'Dismiss',
        'converted_to' => 'Registered as',
        'dismissed_by' => 'Dismissed by :name:',
        'none' => 'No suggestion here.',
    ],
    'errors' => [
        'owner' => 'Choose an active owner of the organization (the suggestion may also have been decided meanwhile).',
        'reason' => 'Say why the suggestion is dismissed.',
    ],
];
