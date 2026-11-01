<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'navigation_group' => 'CMDB',
    'label' => 'Item type',
    'plural_label' => 'Item types',
    'name' => 'Name',
    'key' => 'Key',
    'key_help' => 'Lowercase letters, digits and underscores, e.g. server. Used by imports; cannot be changed later.',
    'description' => 'Description',
    'active' => 'Active',
    'active_help' => 'Inactive types cannot be chosen for new items; existing items keep them.',
    'attributes' => 'Attributes',
    'attributes_help' => 'What items of this type record, e.g. operating system or IP address. Changing them keeps existing values; removed attributes are no longer shown.',
    'items' => 'Items',
];
