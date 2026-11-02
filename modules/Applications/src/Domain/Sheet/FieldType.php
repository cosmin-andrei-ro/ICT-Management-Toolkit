<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Domain\Sheet;

/**
 * The kinds of field the documentation sheet is made of.
 */
enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Email = 'email';
    case Url = 'url';
    case Date = 'date';
    // One value of an editable list (OptionCatalog), or free text when "Other" is allowed.
    case Option = 'option';
    // Any of a fixed set of choices (tick boxes).
    case Choices = 'choices';
    case User = 'user';
    case Supplier = 'supplier';
    case Contract = 'contract';
    // Rows of simple fields.
    case Table = 'table';
    // One contact per entry of an editable list (e.g. support teams).
    case Contacts = 'contacts';
}
