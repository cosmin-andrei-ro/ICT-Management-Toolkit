<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    'menu' => 'Suppliers',
    'title' => 'Suppliers',
    'fields' => [
        'name' => 'Name',
        'legal_name' => 'Legal name',
        'tax_id' => 'Tax ID',
        'customer_number' => 'Our customer number',
        'website' => 'Website',
        'support_email' => 'Support email',
        'support_phone' => 'Support phone',
        'support_hours' => 'Support hours',
        'notes' => 'Notes',
        'active' => 'Active',
    ],
    'contacts' => [
        'title' => 'Contacts',
        'name' => 'Name',
        'role' => 'Role',
        'email' => 'Email',
        'phone' => 'Phone',
        'notes' => 'Notes',
        'add' => 'Add contact',
        'edit' => 'Edit',
        'save' => 'Save contact',
        'cancel' => 'Cancel',
        'remove' => 'Remove',
        'remove_confirm' => 'Remove this contact?',
        'empty' => 'No contacts yet.',
        'privacy' => 'Business contact details only.',
    ],
    'ui' => [
        'intro' => 'Companies IT buys from or relies on, with whom to call when something breaks.',
        'new' => 'New supplier',
        'edit' => 'Edit',
        'back' => '← Suppliers',
        'search' => 'Search by name or tax ID',
        'show_inactive' => 'Show inactive',
        'empty' => 'No suppliers match.',
        'save' => 'Save',
        'details' => 'Details',
        'support' => 'Support',
        'contracts' => 'Contracts',
        'no_contracts' => 'No contracts with this supplier.',
        'new_contract' => 'New contract',
        'inactive' => 'Inactive',
        'delete' => 'Delete',
        'delete_confirm' => 'Delete this supplier? To keep it visible on old records, mark it inactive instead.',
        'has_contracts' => 'This supplier still has contracts. Delete them first, or mark the supplier inactive.',
        'name_taken' => 'Another supplier already has this name.',
    ],
];
