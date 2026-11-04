<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'title' => 'Import',
    'intro' => 'Fill the CMDB from a spreadsheet. Check the preview first: nothing is saved until you confirm, and a file with errors is not imported at all.',
    'kind' => 'What does the file contain?',
    'kinds' => [
        'items' => 'Items: one row per item. A row whose identifier already exists updates that item; the others are created.',
        'relationships' => 'Relationships between existing items, found by their identifiers. Import the items first.',
    ],
    'columns_items' => 'First row: the column names. Columns (only type and name are needed for a new item; an empty cell keeps the current value of an existing item):',
    'columns_relationships' => 'First row: the column names. Relationship: depends on, runs on, uses, is part of, connects to.',
    'attribute_columns' => 'Attribute columns, named by key, per type:',
    'file' => 'File (CSV or XLSX)',
    'file_help' => 'Up to :rows rows and :mb MB. CSV separated by commas or semicolons, UTF-8.',
    'preview' => 'Preview',
    'preview_title' => 'Preview',
    'line' => 'Line',
    'row' => 'Row',
    'result' => 'Result',
    'actions' => [
        'create' => 'New',
        'update' => 'Update',
        'unchanged' => 'Already exists',
        'error' => 'Error',
    ],
    'fix_first' => ':count row(s) have errors. Fix them in the file and preview again; only those rows are shown.',
    'confirm' => '{1} Import 1 row|[2,*] Import :count rows',
    'done' => '{0} Nothing to import.|{1} 1 row imported.|[2,*] :count rows imported.',
    'has_errors' => 'The file changed and now has errors. Nothing was imported.',
    'wrong_format' => 'Choose a CSV or an XLSX file.',
    'unreadable' => 'This file cannot be read. Save it again as CSV (UTF-8) or XLSX and retry.',
    'no_header' => 'The first row must contain the column names.',
    'too_many_rows' => 'At most :max rows can be imported at once. Split the file.',
    'errors' => [
        'duplicate_identifier' => 'The same identifier is already on line :line.',
        'type' => 'Unknown or inactive type.',
        'type_change' => 'The item exists with another type; change its type in the CMDB first.',
        'name' => 'A name (up to 255 characters) is needed.',
        'status' => 'Unknown status ":value".',
        'environment' => 'Unknown environment ":value".',
        'criticality' => 'Unknown criticality ":value".',
        'owner' => 'No active person of this organization has the email ":value".',
        'team' => 'No team is named ":value".',
        'source' => 'No item has the identifier ":value".',
        'target' => 'No item has the identifier ":value".',
        'relationship' => 'Unknown relationship ":value".',
        'self' => 'An item cannot relate to itself.',
    ],
];
