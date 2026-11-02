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
    // The template's data categories with the elements processed (2.1).
    case DataCategories = 'data_categories';
    // An uploaded image of a diagram and its source (.drawio) file.
    case Diagram = 'diagram';
    // Which roles (3.3) may use which functionality (2.3): the permissions matrix (3.4).
    case Permissions = 'permissions';
    // CMDB items linked to the version through Core's ConfigurationItems contract (not stored in the content).
    case ConfigurationItems = 'configuration_items';
}
