<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'types' => [
        'depends_on' => ['forward' => 'depends on', 'inverse' => 'is needed by'],
        'runs_on' => ['forward' => 'runs on', 'inverse' => 'hosts'],
        'uses' => ['forward' => 'uses', 'inverse' => 'is used by'],
        'part_of' => ['forward' => 'is part of', 'inverse' => 'contains'],
        'connects_to' => ['forward' => 'connects to', 'inverse' => 'is connected to'],
    ],
    'ui' => [
        'title' => 'Relationships',
        'graph' => 'Dependency map',
        'graph_label' => 'Map of this item and the items related to it, up to two steps away',
        'graph_help' => 'This item and its neighbours, up to two steps away. Colour shows criticality. Click an item to open it; scroll to zoom.',
        'none' => 'No relationships yet.',
        'add' => 'Add a relationship',
        'add_help' => 'Read it as a sentence: "payroll-app runs on vm-01". Choose "is…" to put this item on the right-hand side.',
        'direction' => 'Direction',
        'outgoing' => '(this item) …',
        'incoming' => '… (this item)',
        'type' => 'Relationship',
        'find' => 'Find an item',
        'other' => 'Item',
        'choose' => 'Choose an item',
        'add_button' => 'Add',
        'remove' => 'Remove',
        'invalid' => 'This relationship is not possible.',
    ],
    'impact' => [
        'affected' => 'Affected if this fails',
        'affected_help' => 'Items that stop working or degrade when this one fails, nearest first.',
        'dependencies' => 'What this needs',
        'dependencies_help' => 'Items this one depends on, directly or through others.',
        'none' => 'Nothing.',
        'depth' => '{1} direct|[2,*] :count steps away',
    ],
];
