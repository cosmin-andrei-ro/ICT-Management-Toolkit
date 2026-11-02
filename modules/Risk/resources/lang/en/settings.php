<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'navigation_group' => 'Risk',
    'page' => [
        'title' => 'Risk method',
        'likelihood' => 'Likelihood levels',
        'impact' => 'Impact levels',
        'level_label' => 'Level :level',
        'level_description' => 'Level :level means',
        'bands' => 'Risk levels and approval',
        'bands_help' => 'Risk = likelihood × impact, from 1 to 25. Each level starts at the score given; below "medium" a risk is low.',
        'medium_from' => 'Medium from',
        'high_from' => 'High from',
        'critical_from' => 'Critical from',
        'order' => 'Each level must start above the previous one.',
        'approval_from' => 'Accepting a risk needs a risk manager from',
        'approval_help' => 'Score of the residual risk from which the owner alone may not accept it.',
        'review_months' => 'Reassess risks every (months)',
        'save' => 'Save',
        'saved' => 'Risk method saved.',
    ],
    'catalog' => [
        'label' => 'catalog entry',
        'plural_label' => 'Threats and vulnerabilities',
        'kind' => 'Kind',
        'name' => 'Name',
        'description' => 'Description',
        'active' => 'Active',
        'active_help' => 'Inactive entries are no longer offered; risks that use them keep them.',
        'taken' => 'The catalog already has an entry of this kind with this name.',
    ],
    'defaults' => [
        'likelihood' => [
            1 => ['label' => 'Rare', 'description' => 'Could happen only in exceptional circumstances; less than once in 10 years.'],
            2 => ['label' => 'Unlikely', 'description' => 'Could happen; about once in 5 to 10 years.'],
            3 => ['label' => 'Possible', 'description' => 'Might well happen; about once in 1 to 5 years.'],
            4 => ['label' => 'Likely', 'description' => 'Expected to happen; about once a year.'],
            5 => ['label' => 'Almost certain', 'description' => 'Happens several times a year, or is happening.'],
        ],
        'impact' => [
            1 => ['label' => 'Negligible', 'description' => 'No noticeable effect on the business, customers or compliance.'],
            2 => ['label' => 'Minor', 'description' => 'Small, short disruption or loss, absorbed by the teams; no external visibility.'],
            3 => ['label' => 'Moderate', 'description' => 'Noticeable disruption or loss; some customers affected; a possible reportable breach.'],
            4 => ['label' => 'Major', 'description' => 'Core services stopped beyond their RTO, significant loss, regulatory sanctions likely, media coverage.'],
            5 => ['label' => 'Severe', 'description' => 'The organization\'s viability, licence or safety is at stake; lasting loss of trust.'],
        ],
    ],
];
