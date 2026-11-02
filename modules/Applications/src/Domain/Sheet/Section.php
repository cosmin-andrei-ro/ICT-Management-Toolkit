<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Domain\Sheet;

/**
 * A numbered section of the sheet, as in the template ("1.4 Environments").
 * Stored in the version's content under its key ("1_4").
 */
final readonly class Section
{
    /**
     * @param  list<Field>  $fields
     */
    public function __construct(
        public string $key,
        public int $chapter,
        public array $fields,
    ) {}

    public function number(): string
    {
        return $this->key === 'doc' ? '' : str_replace('_', '.', $this->key);
    }

    public function title(): string
    {
        return __('applications::sheet.sections.'.$this->key.'.title');
    }

    public function guidance(): ?string
    {
        $key = 'applications::sheet.sections.'.$this->key.'.guidance';

        return trans()->has($key) ? self::text($key) : null;
    }

    public function label(Field $field, ?Field $column = null): string
    {
        return __('applications::sheet.fields.'.$this->key.'.'.$field->key.($column !== null ? '.'.$column->key : ''));
    }

    /**
     * The template's example, shown as a placeholder.
     */
    public function example(Field $field, ?Field $column = null): ?string
    {
        $key = 'applications::sheet.examples.'.$this->key.'.'.$field->key.($column !== null ? '.'.$column->key : '');

        return trans()->has($key) ? self::text($key) : null;
    }

    private static function text(string $key): ?string
    {
        $text = __($key);

        return is_string($text) ? $text : null;
    }
}
