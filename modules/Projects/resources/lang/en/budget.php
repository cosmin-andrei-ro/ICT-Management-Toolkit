<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'heading' => 'Budget',
    'set_budget' => 'Set the budget',
    'approved' => 'Approved budget',
    'currency' => 'Currency',
    'spent' => 'Spent',
    'remaining' => 'Remaining',
    'used' => 'Used',
    'over' => 'over budget',
    'add_cost' => 'Add the cost',
    'delete_confirm' => 'Delete this cost?',
    'categories' => [
        'external_labour' => 'External labour',
        'licences' => 'Licences',
        'hardware' => 'Hardware',
        'services' => 'Services',
        'other' => 'Other',
    ],
    'fields' => [
        'category' => 'Category',
        'description' => 'Description',
        'amount' => 'Amount',
        'incurred_on' => 'Date',
        'supplier' => 'Supplier',
        'invoice' => 'Invoice number',
    ],
    'errors' => [
        'currency_locked' => 'Costs are recorded in :currency; the currency cannot change.',
        'supplier' => 'Choose a supplier of this organization.',
    ],
];
