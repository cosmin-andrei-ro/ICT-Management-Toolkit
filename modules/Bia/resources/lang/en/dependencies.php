<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'title' => 'What the process needs',
    'help' => 'IT systems from the CMDB, suppliers, places, other processes, and the people or roles without whom the process stops. IT plans its recovery for these.',
    'systems' => 'IT systems and services (CMDB)',
    'cmdb_off' => 'The CMDB is not enabled.',
    'find_item' => 'Search the CMDB by name or identifier',
    'kinds' => [
        'supplier' => 'Suppliers',
        'location' => 'Locations',
        'process' => 'Processes it depends on',
        'role' => 'Key roles',
        'person' => 'Key people',
    ],
    'role_name' => 'Role, e.g. Payroll specialist',
    'role_minimum' => 'At least',
    'minimum' => 'at least :count person|at least :count people',
    'add' => 'Add',
    'remove' => 'Remove',
    'none' => 'None.',
    'invalid' => 'This cannot be added: it is already listed, it belongs to another organization, or it is the process itself.',
];
