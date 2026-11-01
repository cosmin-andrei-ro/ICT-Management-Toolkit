<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'menu' => 'CMDB',
    'title' => 'Configuration items',
    'statuses' => [
        'planned' => 'Planned',
        'in_use' => 'In use',
        'maintenance' => 'In maintenance',
        'retired' => 'Retired',
    ],
    'environments' => [
        'production' => 'Production',
        'test' => 'Test',
        'development' => 'Development',
        'disaster_recovery' => 'Disaster recovery',
    ],
    'criticalities' => [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ],
    'fields' => [
        'type' => 'Type',
        'name' => 'Name',
        'identifier' => 'Identifier',
        'status' => 'Status',
        'environment' => 'Environment',
        'criticality' => 'Criticality',
        'owner' => 'Owner',
        'team' => 'Support team',
        'description' => 'Description',
    ],
    'ui' => [
        'intro' => 'Everything IT runs: services, applications, servers, databases, network devices… and how they depend on each other.',
        'new' => 'New item',
        'edit' => 'Edit',
        'back' => '← Configuration items',
        'search' => 'Search by name, identifier or attribute',
        'all_types' => 'All types',
        'all_statuses' => 'All statuses',
        'all_environments' => 'All environments',
        'all_criticalities' => 'All criticalities',
        'empty' => 'No items match.',
        'identifier_help' => 'Hostname, asset tag or another unique name. Imports match items by it.',
        'attributes' => 'Attributes of this type',
        'save' => 'Save',
        'details' => 'Details',
        'yes' => 'Yes',
        'no' => 'No',
        'delete' => 'Delete',
        'delete_confirm' => 'Delete this item and its relationships? To keep its history visible, set its status to Retired instead.',
        'identifier_taken' => 'Another item already has this identifier.',
        'invalid_owner_or_team' => 'Choose an owner and a team of this organization.',
    ],
];
