<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'navigation_group' => 'Business impact analysis',
    'category' => [
        'label' => 'Impact category',
        'plural_label' => 'Impact categories',
        'name' => 'Name',
        'description' => 'Description',
        'levels' => 'What each level means',
        'level' => 'Level :level',
        'sort_order' => 'Order',
        'active' => 'Active',
        'active_help' => 'Inactive categories stay on approved assessments but are not scored any more.',
        'taken' => 'Another category already has this name.',
    ],
    'horizon' => [
        'label' => 'Time horizon',
        'plural_label' => 'Time horizons',
        'label_field' => 'Label',
        'minutes' => 'Duration (minutes)',
        'minutes_help' => '240 = 4 hours, 1440 = 1 day, 10080 = 1 week. Fixed once assessments use it.',
        'active' => 'Active',
        'taken' => 'Another horizon already has this duration.',
    ],
    'page' => [
        'title' => 'BIA settings',
        'level' => 'Unacceptable impact level',
        'level_help' => 'From this score, an interruption is unacceptable; it sets the MTPD. Applies to assessments approved from now on.',
        'review_months' => 'Review every (months)',
        'review_help' => 'After approval, a process is due for review after this many months.',
        'save' => 'Save',
        'saved' => 'Saved.',
    ],
];
