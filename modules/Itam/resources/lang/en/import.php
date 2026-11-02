<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'title' => 'Import assets',
    'intro' => 'Fill the register from a spreadsheet: the first inventory, or a delivery. Check the preview first: nothing is saved until you confirm, and a file with errors is not imported at all.',
    'columns' => 'First row: the column names. A row whose asset tag already exists (or, without a tag, whose serial number exists) updates that asset; the others are created. Only the category is needed for a new asset; an empty cell keeps the current value of an existing one.',
    'column_notes' => 'Status: ordered or in stock, for new assets only. Holder: the email of the person who has it; the asset is handed to them. Location: the full name ("Site > Room") or a unique name. Supplier: its name. Dates: YYYY-MM-DD.',
    'attribute_columns' => 'Attribute columns, named by key, per category:',
    'file' => 'File (CSV or Excel)',
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
    'wrong_format' => 'Choose a CSV or an Excel (.xlsx) file.',
    'unreadable' => 'This file cannot be read. Save it again as CSV (UTF-8) or Excel and retry.',
    'no_header' => 'The first row must contain the column names.',
    'too_many_rows' => 'At most :max rows can be imported at once. Split the file.',
    'assigned_by_import' => 'Imported',
    'kind' => 'What does the file contain?',
    'kinds' => [
        'assets' => 'Assets: one row per device.',
        'licenses' => 'Software licences: one row per licence. Keys are not imported; enter them on each licence.',
    ],
    'licenses' => [
        'title' => 'Import licences',
        'columns' => 'First row: the column names. A row whose product and licence name already exist updates that licence; the others are created, with their product if it is new.',
        'column_notes' => 'Model: per_user, per_device or site. Quantity: the seats bought (not for site licences). Supplier: its name. Dates: YYYY-MM-DD; an empty expiry means perpetual.',
        'errors' => [
            'product' => 'A product name (up to 255 characters) is needed.',
            'name' => 'A licence name (up to 255 characters) is needed.',
            'duplicate' => 'The same product and licence name are already on line :line.',
            'model' => 'Unknown model ":value": use per_user, per_device or site.',
            'quantity' => 'A number of seats (1 or more) is needed.',
        ],
    ],
    'errors' => [
        'duplicate' => 'The same :column is already on line :line.',
        'serial_taken' => 'Another asset (:tag) already has this serial number.',
        'category' => 'Unknown or inactive category.',
        'category_change' => 'The asset exists with another category; change it on the asset page first.',
        'status' => 'Unknown status ":value": use ordered or in stock.',
        'status_change' => 'The status of an existing asset changes on its page, not by import.',
        'holder' => 'No active person of this organization has the email ":value".',
        'cannot_hand_out' => 'The asset is ":status" and cannot be handed out.',
        'location' => 'No active location is named ":value".',
        'supplier' => 'No supplier is named ":value".',
        'too_long' => ':column is longer than :max characters.',
        'date' => ':column must be a date as YYYY-MM-DD, not ":value".',
        'cost' => 'Purchase cost ":value" is not an amount.',
        'currency' => 'A purchase cost needs a three-letter currency, for example EUR.',
    ],
];
