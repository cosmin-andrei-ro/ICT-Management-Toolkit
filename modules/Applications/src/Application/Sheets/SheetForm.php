<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Sheets;

use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Applications\Application\Options\OptionCatalog;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Applications\Domain\Sheet\Field;
use Modules\Applications\Domain\Sheet\FieldType;
use Modules\Applications\Domain\Sheet\Section;
use Modules\Applications\Domain\Sheet\SheetSchema;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Turns a section of the sheet into form values and back: validation rules,
 * normalized storage (free "Other" text resolved, empty rows dropped,
 * references checked within the organization) and completeness.
 */
final readonly class SheetForm
{
    /** The select value meaning "Other": the text is typed next to it. */
    public const string OTHER = '__other';

    public const int MAX_ROWS = 200;

    public function __construct(
        private OptionCatalog $options,
        private OrganizationContext $context,
    ) {}

    /**
     * @param  array<mixed>  $stored
     * @return array<string, mixed>
     */
    public function toForm(Section $section, array $stored): array
    {
        $form = [];

        foreach ($section->fields as $field) {
            $value = $stored[$field->key] ?? null;

            if ($field->type === FieldType::Table) {
                $form[$field->key] = array_map(
                    fn (mixed $row): array => $this->rowToForm($field, is_array($row) ? $row : []),
                    is_array($value) ? array_values($value) : [],
                );

                continue;
            }

            $form += $this->valueToForm($field, $value);
        }

        return $form;
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyRow(Field $table): array
    {
        return $this->rowToForm($table, []);
    }

    /**
     * Rules for the section's values under $prefix ("form.1_4"). Nothing is
     * required here: drafts are saved incomplete, completeness is checked on
     * submission.
     *
     * @return array<string, mixed>
     */
    public function rules(Section $section, string $prefix): array
    {
        $rules = [$prefix => ['array']];

        foreach ($section->fields as $field) {
            if ($field->type === FieldType::Table) {
                $rules["{$prefix}.{$field->key}"] = ['array', 'max:'.self::MAX_ROWS];

                foreach ($field->columns as $column) {
                    $rules += $this->valueRules($column, "{$prefix}.{$field->key}.*");
                }

                continue;
            }

            $rules += $this->valueRules($field, $prefix);
        }

        return $rules;
    }

    /**
     * @param  array<mixed>  $input  Validated with rules().
     * @return array<string, mixed>
     *
     * @throws ValidationException for a person, supplier or contract outside the organization
     */
    public function fromForm(Section $section, array $input, string $prefix): array
    {
        $stored = [];

        foreach ($section->fields as $field) {
            if ($field->type === FieldType::Table) {
                $rows = [];

                foreach (is_array($input[$field->key] ?? null) ? $input[$field->key] : [] as $index => $row) {
                    $values = [];

                    foreach ($field->columns as $column) {
                        $values[$column->key] = $this->valueFromForm($column, is_array($row) ? $row : [], "{$prefix}.{$field->key}.{$index}");
                    }

                    if (array_filter($values, fn (mixed $value): bool => $value !== null && $value !== []) !== []) {
                        $rows[] = $values;
                    }
                }

                $stored[$field->key] = $rows;

                continue;
            }

            $stored[$field->key] = $this->valueFromForm($field, $input, $prefix);
        }

        return $stored;
    }

    /**
     * Sections with a required value missing, in schema order.
     *
     * @return list<Section>
     */
    public function incomplete(Sheet $sheet): array
    {
        return array_values(array_filter(SheetSchema::sections(), function (Section $section) use ($sheet): bool {
            $values = $sheet->section($section->key);

            foreach ($section->fields as $field) {
                if ($field->type === FieldType::Table) {
                    $rows = is_array($values[$field->key] ?? null) ? $values[$field->key] : [];

                    if ($field->required && $rows === []) {
                        return true;
                    }

                    foreach ($rows as $row) {
                        foreach ($field->columns as $column) {
                            if ($column->required && self::blank(is_array($row) ? ($row[$column->key] ?? null) : null)) {
                                return true;
                            }
                        }
                    }

                    continue;
                }

                if ($field->required && self::blank($values[$field->key] ?? null)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param  array<mixed>  $stored
     * @return array<string, mixed>
     */
    private function rowToForm(Field $table, array $stored): array
    {
        $row = [];

        foreach ($table->columns as $column) {
            $row += $this->valueToForm($column, $stored[$column->key] ?? null);
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function valueToForm(Field $field, mixed $value): array
    {
        return match ($field->type) {
            FieldType::Choices => [$field->key => is_array($value) ? array_values(array_filter($value, is_string(...))) : []],
            FieldType::Contacts => [$field->key => is_array($value) ? array_filter($value, is_string(...)) : []],
            FieldType::Option => $this->optionToForm($field, is_string($value) ? $value : ''),
            default => [$field->key => is_string($value) ? $value : ''],
        };
    }

    /**
     * @return array<string, string>
     */
    private function optionToForm(Field $field, string $value): array
    {
        $isOther = $value !== '' && $field->allowOther && ! array_key_exists($value, $this->options->choices((string) $field->list));

        return [
            $field->key => $isOther ? self::OTHER : $value,
            $field->key.'_other' => $isOther ? $value : '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function valueRules(Field $field, string $prefix): array
    {
        $path = "{$prefix}.{$field->key}";

        return match ($field->type) {
            FieldType::Text => [$path => ['nullable', 'string', 'max:'.$field->max]],
            FieldType::Textarea => [$path => ['nullable', 'string', 'max:'.$field->max]],
            FieldType::Email => [$path => ['nullable', 'string', 'max:255', 'email']],
            // Links are shown as links: web addresses only, never javascript: or data:.
            FieldType::Url => [$path => ['nullable', 'string', 'max:'.$field->max, 'url:http,https']],
            FieldType::Date => [$path => ['nullable', 'date_format:Y-m-d']],
            FieldType::Option => [
                $path => ['nullable', 'string', Rule::in([...array_map(strval(...), array_keys($this->options->choices((string) $field->list))), ...($field->allowOther ? [self::OTHER] : [])])],
                $path.'_other' => ['nullable', 'string', 'max:255'],
            ],
            FieldType::Choices => [$path => ['array'], $path.'.*' => [Rule::in($field->choices)]],
            FieldType::User, FieldType::Supplier, FieldType::Contract => [$path => ['nullable', 'uuid']],
            FieldType::Contacts => [$path => ['array'], $path.'.*' => ['nullable', 'string', 'max:255']],
            FieldType::Table => [],
        };
    }

    /**
     * @param  array<mixed>  $input
     */
    private function valueFromForm(Field $field, array $input, string $prefix): mixed
    {
        $value = $input[$field->key] ?? null;

        switch ($field->type) {
            case FieldType::Choices:
                return array_values(array_intersect($field->choices, is_array($value) ? array_filter($value, is_string(...)) : []));
            case FieldType::Contacts:
                $contacts = [];

                foreach (array_keys($this->options->values((string) $field->list)->keyBy('key')->all()) as $key) {
                    $contact = is_array($value) && is_string($value[$key] ?? null) ? trim($value[$key]) : '';

                    if ($contact !== '') {
                        $contacts[(string) $key] = $contact;
                    }
                }

                return $contacts;
            case FieldType::Option:
                if ($value === self::OTHER) {
                    $other = $input[$field->key.'_other'] ?? null;

                    return is_string($other) && trim($other) !== '' ? trim($other) : null;
                }

                return is_string($value) && $value !== '' ? $value : null;
            case FieldType::User:
            case FieldType::Supplier:
            case FieldType::Contract:
                $id = is_string($value) && $value !== '' ? $value : null;

                if ($id !== null && ! $this->exists($field->type, $id)) {
                    throw ValidationException::withMessages(["{$prefix}.{$field->key}" => __('applications::sheet.errors.unknown_reference')]);
                }

                return $id;
            default:
                return is_string($value) && trim($value) !== '' ? trim($value) : null;
        }
    }

    private function exists(FieldType $type, string $id): bool
    {
        return match ($type) {
            FieldType::User => User::query()->whereKey($id)->whereHas('organizations', fn ($query) => $query->whereKey($this->context->requireId()))->exists(),
            // Scoped models: one of another organization is not found.
            FieldType::Supplier => Supplier::query()->whereKey($id)->exists(),
            FieldType::Contract => Contract::query()->whereKey($id)->exists(),
            default => false,
        };
    }

    private static function blank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
