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
    'anonymized_name' => 'Former user #:reference',
    'anonymize' => [
        'title' => 'Anonymize a person',
        'intro' => 'For someone who asks to be forgotten and whose data the organization no longer has to keep. The records they took part in stay, under "Former user", so the history of tickets, risks or audits remains complete.',
        'action' => 'Anonymize…',
        'description' => 'This cannot be undone. The person loses their name, e-mail address and every way to sign in; they leave this organization; their name and address are also removed from the audit log, with the IP addresses of their sign-ins. Export their data first if they asked for it.',
        'confirmation' => 'Type their e-mail address to confirm',
        'code' => 'Code from your authenticator app',
        'submit' => 'Anonymize',
        'done' => 'The person was anonymized.',
        'errors' => [
            'already' => 'This person is already anonymized.',
            'self' => 'You cannot anonymize yourself.',
            'not_member' => 'This person is not a member of this organization.',
            'other_organizations' => 'This person also belongs to other organizations. Each must remove them first: the account is shared.',
            'last_administrator' => 'This person is the last administrator.',
            'confirmation' => 'The e-mail address does not match.',
            'code' => 'The code is not right.',
        ],
    ],
];
