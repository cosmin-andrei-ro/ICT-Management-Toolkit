<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Domain\Sheet;

/**
 * One field of the sheet. Its label, hint and example come from the
 * translations, by section and key.
 */
final readonly class Field
{
    /**
     * @param  list<string>  $choices  For Choices fields.
     * @param  list<Field>  $columns  For Table fields: simple fields only.
     */
    public function __construct(
        public string $key,
        public FieldType $type,
        public bool $required = false,
        public ?string $list = null,
        public bool $allowOther = false,
        public array $choices = [],
        public array $columns = [],
        public int $max = 255,
    ) {}

    public static function text(string $key, bool $required = false, int $max = 255): self
    {
        return new self($key, FieldType::Text, $required, max: $max);
    }

    public static function textarea(string $key, bool $required = false): self
    {
        return new self($key, FieldType::Textarea, $required, max: 10000);
    }

    public static function email(string $key): self
    {
        return new self($key, FieldType::Email);
    }

    public static function url(string $key, bool $required = false): self
    {
        return new self($key, FieldType::Url, $required, max: 2000);
    }

    public static function date(string $key): self
    {
        return new self($key, FieldType::Date);
    }

    public static function option(string $key, string $list, bool $required = false, bool $allowOther = false): self
    {
        return new self($key, FieldType::Option, $required, $list, $allowOther);
    }

    /**
     * @param  list<string>  $choices
     */
    public static function choices(string $key, array $choices): self
    {
        return new self($key, FieldType::Choices, choices: $choices);
    }

    public static function user(string $key, bool $required = false): self
    {
        return new self($key, FieldType::User, $required);
    }

    public static function supplier(string $key): self
    {
        return new self($key, FieldType::Supplier);
    }

    public static function contract(string $key): self
    {
        return new self($key, FieldType::Contract);
    }

    /**
     * @param  list<Field>  $columns
     */
    public static function table(string $key, array $columns, bool $required = false): self
    {
        return new self($key, FieldType::Table, $required, columns: $columns);
    }

    public static function dataCategories(string $key): self
    {
        return new self($key, FieldType::DataCategories, max: 2000);
    }

    public static function diagram(string $key): self
    {
        return new self($key, FieldType::Diagram);
    }

    public static function permissions(string $key): self
    {
        return new self($key, FieldType::Permissions);
    }

    public static function contacts(string $key, string $list): self
    {
        return new self($key, FieldType::Contacts, list: $list);
    }
}
