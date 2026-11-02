<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'title' => 'Evidence',
    'intro' => 'What shows the control works: records of the toolkit (an approved change, an exercise, a risk…) or documents (a report, a configuration export, a policy).',
    'none' => 'No evidence yet.',
    'document' => 'Document',
    'kind' => 'Kind of record',
    'note' => 'What it shows',
    'note_example' => 'e.g. Restore test of the payroll database within the RTO',
    'link_title' => 'Link a record of the toolkit',
    'link' => 'Link',
    'nothing_found' => 'Nothing found that you may open.',
    'upload_title' => 'Upload a document',
    'upload' => 'Upload',
    'document_title' => 'Title',
    'document_title_example' => 'e.g. MFA enrolment report, March',
    'valid_until_field' => 'Valid until',
    'valid_until' => 'valid until :date',
    'expired' => 'expired on :date',
    'added' => 'Added by :name on :date',
    'from_test' => 'recorded with a test',
    'not_visible' => '(you cannot open this record, or it was removed)',
    'remove' => 'Remove',
    'remove_confirm' => 'Remove this evidence? It stays in the history.',
    'errors' => [
        'link' => 'This record cannot be linked, or it is already linked.',
    ],
    'results' => [
        'effective' => 'Effective',
        'partially_effective' => 'Partly effective',
        'ineffective' => 'Not effective',
    ],
    'tests' => [
        'title' => 'Tests',
        'none' => 'Not tested yet.',
        'record' => 'Record a test',
        'save' => 'Record test',
        'tested_on' => 'Tested on',
        'result' => 'Result',
        'method' => 'How it was tested',
        'method_example' => 'e.g. Sample of 25 remote sign-ins from the identity provider log, each checked for a second factor',
        'notes' => 'What was found',
        'notes_example' => 'e.g. Two service accounts sign in without a second factor; both are in the exception register',
        'document' => 'Document of the test (optional)',
        'document_title' => 'Test of :date',
        'interval' => 'Test every (months)',
        'interval_help' => 'The owner is reminded before each test is due. Leave empty for a control that is not tested periodically.',
        'every' => 'Tested every month|Tested every :count months',
        'next' => 'next test :date',
        'next_column' => 'Next test',
        'not_scheduled' => 'No periodic test.',
        'overdue' => 'Overdue',
        'overdue_filter' => 'Test overdue',
    ],
    'mail' => [
        'subject' => 'Control test due: :control',
        'line' => 'The control :control, which you own, is due for its periodic test on :date. Record the test and its evidence on the control\'s page.',
        'open' => 'Open the control',
    ],
];
