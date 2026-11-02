<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'menu' => 'Risk register',
    'title' => 'Risk register',
    'categories' => [
        'information_security' => 'Information security',
        'availability' => 'Availability and continuity',
        'compliance' => 'Legal and compliance',
        'supplier' => 'Suppliers and third parties',
        'operational' => 'Operational',
        'project' => 'Projects and change',
    ],
    'statuses' => [
        'open' => 'Open',
        'closed' => 'Closed',
    ],
    'kinds' => [
        'threat' => 'Threat',
        'vulnerability' => 'Vulnerability',
    ],
    'asset_types' => [
        'application' => 'Application',
        'configuration_item' => 'CMDB item',
        'process' => 'Business process',
        'supplier' => 'Supplier',
    ],
    'levels' => [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ],
    'fields' => [
        'risk' => 'Risk',
        'title' => 'Risk scenario',
        'description' => 'Description',
        'category' => 'Category',
        'owner' => 'Risk owner',
        'status' => 'Status',
        'level' => 'Level',
        'inherent' => 'Inherent risk',
        'likelihood' => 'Likelihood',
        'impact' => 'Impact',
        'existing_controls' => 'Controls already in place',
        'next_review' => 'Next review',
    ],
    'examples' => [
        'title' => 'e.g. Ransomware encrypts the payroll database',
        'description' => 'e.g. An attacker gains access through a phishing e-mail and encrypts the servers of the payroll application; salaries cannot be paid on time.',
        'controls' => 'e.g. Daily backups kept offline; endpoint protection; MFA on remote access',
    ],
    'ui' => [
        'intro' => 'Risk scenarios, what they concern, how likely and how severe they are before treatment.',
        'new' => 'New risk',
        'edit' => 'Edit',
        'save' => 'Save',
        'back' => '← Risk register',
        'search' => 'Search',
        'all' => 'All',
        'show_closed' => 'Show closed risks',
        'none' => 'No risk.',
        'heat_map' => 'Heat map',
        'map_kind' => 'Risk shown',
        'map_current' => 'Current (after treatment)',
        'map_inherent' => 'Before treatment',
        'heat_caption' => 'Open risks by likelihood (rows, 5 at the top) and impact (columns).',
        'heat_help' => 'Rows: likelihood, columns: impact. Current: the residual risk where it is assessed, the inherent risk otherwise. Click a cell to list its risks.',
        'cell' => 'Likelihood :likelihood, impact :impact: :count risk(s)',
        'not_assessed' => 'Not assessed',
        'owner_help' => 'The owner assesses the risk and, later, proposes its treatment.',
        'concerns' => 'What it concerns',
        'no_assets' => 'Nothing linked yet.',
        'asset_type' => 'Kind',
        'find_asset' => 'Search by name',
        'link' => 'Link',
        'remove' => 'Remove',
        'assessment' => 'Assessment before treatment',
        'threats' => 'Threats',
        'vulnerabilities' => 'Vulnerabilities',
        'save_assessment' => 'Save the assessment',
    ],
    'mine' => [
        'menu' => 'My risks',
        'title' => 'My risks',
        'intro' => 'Risks you own: keep their assessment up to date.',
    ],
    'errors' => [
        'invalid' => 'That change could not be made. Check the entry and try again.',
    ],
];
