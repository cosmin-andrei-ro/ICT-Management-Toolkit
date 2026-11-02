<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'menu' => 'Application portfolio',
    'title' => 'Application portfolio',
    'linked' => 'Application: :application (:code)',
    'statuses' => [
        'draft' => 'Draft',
        'submitted' => 'Waiting for approval',
        'returned' => 'Returned',
        'approved' => 'In force',
        'superseded' => 'Superseded',
    ],
    'approval_roles' => [
        'business' => 'Business owner',
        'technical' => 'IT / application owner',
        'security' => 'Security officer',
    ],
    'fields' => [
        'name' => 'Application name',
        'code' => 'Application code',
        'department' => 'Department',
        'department_owner' => 'Department owner',
        'department_email' => 'Department e-mail',
        'business_owner' => 'Business owner',
        'technical_owner' => 'IT / application owner',
        'deputy' => 'Deputy',
        'owners' => 'Business / IT owner',
        'status' => 'Status',
        'criticality' => 'Business criticality',
        'version' => 'Version',
        'date' => 'Date',
        'author' => 'Author',
        'change' => 'Description of change',
        'approvals' => 'Approvals',
        'last_review' => 'Last review',
        'next_review' => 'Next review',
    ],
    'examples' => [
        'name' => 'e.g. Ticketing Platform',
        'code' => 'e.g. APP-TKT',
        'department_email' => 'e.g. digital.office@company.example',
    ],
    'ui' => [
        'intro' => 'Every application of the company with its technical documentation: how it is built, run and supported.',
        'new' => 'New application',
        'edit' => 'Edit',
        'save' => 'Save',
        'back' => '← Application portfolio',
        'search' => 'Search by name or code',
        'all' => 'All',
        'due_only' => 'Not documented or review overdue',
        'none' => 'No application.',
        'not_documented' => 'Not documented yet',
        'owners_help' => 'The business owner, the IT / application owner and the deputy keep the documentation up to date. The business and IT owners approve major versions with a security officer.',
        'identity' => 'Application',
        'document_control' => 'Document control',
        'history' => 'Change history',
        'no_history' => 'No version in force yet.',
        'minor_logged' => 'Minor change, logged',
        'open_version' => 'Version being written',
        'read' => 'Read version :version',
        'document' => 'Write the documentation',
        'continue' => 'Continue the documentation',
        'update' => 'Update the documentation',
        'delete' => 'Delete',
        'delete_confirm' => 'Delete this application and its documentation? It can be restored by an administrator.',
    ],
    'mine' => [
        'menu' => 'My applications',
        'title' => 'My applications',
        'intro' => 'Applications you own or stand in for: keep their documentation up to date.',
    ],
    'mail' => [
        'submitted' => [
            'subject' => 'Documentation to approve: :application',
            'line' => 'A major version of the documentation of :application waits for your approval.',
        ],
        'returned' => [
            'subject' => 'Documentation returned: :application',
            'line' => 'The documentation of :application was returned for changes.',
        ],
        'in_force' => [
            'subject' => 'Documentation in force: :application :version',
            'line' => 'Version :version of the documentation of :application is now in force.',
        ],
        'comment' => 'Comment: :comment',
        'open' => 'Open the documentation',
    ],
    'errors' => [
        'name_taken' => 'Another application already has this name.',
        'code_taken' => 'Another application already has this code.',
    ],
];
