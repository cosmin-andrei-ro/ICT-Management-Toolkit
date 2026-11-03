<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'abilities' => [
        'read' => 'Read frameworks, requirements, controls, findings and audits',
        'write' => 'Create and change controls and findings, map requirements, link evidence, record tests (not verify findings)',
    ],
    'unknown_owner' => 'The owner must be an active member of the organization.',
    'unknown_requirement' => 'Unknown requirement.',
    'unknown_audit' => 'Unknown audit.',
    'unknown_reference' => 'Unknown owner, control or requirement.',
];
