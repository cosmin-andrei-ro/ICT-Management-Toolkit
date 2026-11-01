<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'navigation_group' => 'Service desk',
    'name' => 'Name',
    'description' => 'Description',
    'active' => 'Active',
    'sort_order' => 'Order',
    'save' => 'Save',
    'saved' => 'Saved.',
    'categories' => [
        'label' => 'Category',
        'plural_label' => 'Categories',
        'parent' => 'Parent category',
    ],
    'queues' => [
        'label' => 'Queue',
        'plural_label' => 'Queues',
        'team' => 'Team',
        'team_help' => 'Only members of this team can be assigned tickets in this queue.',
        'default' => 'Default queue',
        'default_help' => 'New tickets without a queue land here. Only one queue can be the default.',
    ],
    'priority_matrix' => [
        'title' => 'Priority matrix',
        'impact' => 'Impact: :impact',
        'urgency' => 'Urgency: :urgency',
    ],
];
