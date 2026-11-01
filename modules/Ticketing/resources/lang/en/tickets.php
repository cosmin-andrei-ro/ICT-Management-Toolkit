<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'types' => [
        'incident' => 'Incident',
        'service_request' => 'Service request',
        'problem' => 'Problem',
        'change' => 'Change',
    ],
    'statuses' => [
        'new' => 'New',
        'assigned' => 'Assigned',
        'in_progress' => 'In progress',
        'pending' => 'Pending',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
        'cancelled' => 'Cancelled',
    ],
    'impacts' => [
        'high' => 'High — many users or a critical service',
        'medium' => 'Medium — a team or a department',
        'low' => 'Low — one user',
    ],
    'urgencies' => [
        'high' => 'High — work is stopped',
        'medium' => 'Medium — work is slowed down',
        'low' => 'Low — it can wait',
    ],
    'priorities' => [
        'critical' => 'Critical',
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
    ],
    'channels' => [
        'portal' => 'Self-service portal',
        'agent' => 'Service desk',
        'email' => 'Email',
        'api' => 'API',
        'phone' => 'Phone',
    ],
    'resolution_codes' => [
        'fixed' => 'Fixed',
        'workaround' => 'Workaround provided',
        'fulfilled' => 'Request fulfilled',
        'not_reproducible' => 'Could not reproduce',
        'duplicate' => 'Duplicate',
        'user_error' => 'User error / training',
        'no_action_needed' => 'No action needed',
        'implemented' => 'Change implemented',
        'rolled_back' => 'Change rolled back',
    ],
];
