<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'navigation_group' => 'Application portfolio',
    'option' => [
        'label' => 'dropdown value',
        'plural_label' => 'Dropdown values',
        'list' => 'Dropdown',
        'value' => 'Value',
        'key' => 'Key',
        'key_help' => 'Stored in the sheets: lowercase letters, digits and underscores; it cannot change later. Rename the value instead.',
        'taken' => 'This dropdown already has a value with this key.',
        'position' => 'Order',
        'active' => 'Active',
        'active_help' => 'Inactive values are no longer offered; sheets that use them keep them.',
    ],
    'lists' => [
        'application_status' => 'Application status',
        'business_criticality' => 'Business criticality',
        'cartography_profile' => 'Application cartography profile',
        'environments' => 'Environments',
        'document_types' => 'Related documentation types',
        'support_hours' => 'Support hours',
        'support_teams' => 'Internal support teams',
        'data_classification' => 'Data classification',
        'integration_direction' => 'Integration direction',
        'integration_type' => 'Integration type',
        'integration_frequency' => 'Integration frequency',
        'yes_no' => 'Yes / no',
        'authentication_protocols' => 'Authentication protocols',
    ],
];
