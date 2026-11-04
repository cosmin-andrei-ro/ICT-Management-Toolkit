<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'navigation_group' => 'Access',
    'mapping' => [
        'label' => 'AD group mapping',
        'plural_label' => 'AD group mappings',
        'group_dn' => 'AD group (distinguished name)',
        'group_dn_help' => 'For example: CN=IT-Support,OU=Groups,DC=corp,DC=example. Members of nested groups are included.',
        'role' => 'Role in this organization',
        'updated_at' => 'Last changed',
        'duplicate' => 'This group is already mapped to this role.',
    ],
];
