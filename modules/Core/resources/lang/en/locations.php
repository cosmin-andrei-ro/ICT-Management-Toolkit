<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'navigation_group' => 'Organization',
    'label' => 'Location',
    'plural_label' => 'Locations',
    'name' => 'Name',
    'kind' => 'Kind',
    'parent' => 'Inside',
    'parent_help' => 'For example, a room inside a building. Leave empty for a site.',
    'code' => 'Code',
    'address' => 'Address',
    'notes' => 'Notes',
    'active' => 'Active',
    'active_help' => 'Inactive locations stay on existing records but are no longer offered.',
    'path' => 'Full name',
    'kinds' => [
        'site' => 'Site',
        'building' => 'Building',
        'floor' => 'Floor',
        'room' => 'Room',
        'other' => 'Other',
    ],
    'invalid_parent' => 'A location cannot be inside itself or inside one of its own sub-locations.',
    'duplicate' => 'Another location with this name is already in the same place.',
    'in_use' => 'This location still contains other locations. Move or delete them first.',
];
