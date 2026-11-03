<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'sheet' => 'Organization',
    'records' => [
        'department' => 'Department',
        'team' => 'Team',
        'contract' => 'Contract',
        'security_key' => 'Security key or passkey',
    ],
    'roles' => [
        'manager' => 'Manager',
        'lead' => 'Team lead',
        'member' => 'Member',
        'owner' => 'Owner',
        'holder' => 'Their own',
    ],
    'export' => [
        'profile' => 'Account',
        'field' => 'Field',
        'value' => 'Value',
        'record' => 'Record',
        'reference' => 'Reference',
        'title' => 'Title',
        'role' => 'Their part',
        'date' => 'Date',
        'details' => 'Details',
        'comments' => 'Comments',
        'files' => 'Files',
        'activity' => 'Activity',
        'internal' => 'Internal',
        'text' => 'Text',
        'file' => 'File',
        'size' => 'Size (bytes)',
        'directory_username' => 'Directory username',
        'created' => 'Account created',
    ],
    'title' => 'Personal data',
    'intro' => 'Requests from people about their personal data (GDPR): export everything this organization holds about a person.',
    'person' => 'Person',
    'person_help' => 'Members of this organization. Each organization answers for its own data: a person in several organizations is exported from each.',
    'prepare' => 'Prepare the export',
    'prepare_help' => 'The workbook lists the account, every record the person is named in, what they wrote and uploaded, and their activity. It contains personal data: hand it only to the person, through a safe channel. The export is recorded in the audit log.',
    'prepared' => 'The export is being prepared. It appears below when ready.',
    'recent' => 'Your recent exports',
    'none' => 'No exports yet.',
    'for' => 'For :name',
    'unknown_person' => 'Unknown person',
];
