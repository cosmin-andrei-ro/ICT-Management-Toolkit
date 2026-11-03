<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'filter_shape' => 'Filters are given as filter[name]=value.',
    'unknown_filter' => 'Unknown filter ":name". Allowed: :allowed.',
    'filter_value' => 'A filter value is text of at most 200 characters.',
    'unknown_sort' => 'Unknown sort ":name". Allowed: :allowed.',
    'per_page' => 'per_page is a whole number from 1 to :max.',
    'active_member' => 'Choose an active member of the organization.',
    'abilities' => [
        'directory' => 'Read people (name and e-mail), teams, departments and locations',
        'suppliers' => 'Read suppliers, their contacts and contracts',
    ],
];
