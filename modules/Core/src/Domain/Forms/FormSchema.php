<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Forms;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * A small form definition stored as jsonb (custom fields, service request forms;
 * CLAUDE.md §5.2, ADR 0011). Answers are validated against it and stored together
 * with the labels of the moment, so they stay readable if the form changes later.
 */
final readonly class FormSchema
{
    public const int MAX_FIELDS = 30;

    /**
     * @param  list<FormField>  $fields
     */
    public function __construct(public array $fields)
    {
        if (count($fields) > self::MAX_FIELDS) {
            throw new InvalidArgumentException('A form has at most '.self::MAX_FIELDS.' fields.');
        }

        $keys = array_map(fn (FormField $field): string => $field->key, $fields);

        if (count($keys) !== count(array_unique($keys))) {
            throw new InvalidArgumentException('Field keys must be unique within a form.');
        }
    }

    /**
     * @param  mixed  $definition  As stored: a list of field arrays.
     */
    public static function fromArray(mixed $definition): self
    {
        if ($definition === null || $definition === []) {
            return new self([]);
        }

        if (! is_array($definition) || ! array_is_list($definition)) {
            throw new InvalidArgumentException('A form definition is a list of fields.');
        }

        $fields = [];

        foreach ($definition as $field) {
            if (! is_array($field) || ! is_string($field['key'] ?? null) || ! is_string($field['label'] ?? null) || ! is_string($field['type'] ?? null)) {
                throw new InvalidArgumentException('Each field needs a key, a label and a type.');
            }

            $type = FieldType::tryFrom($field['type']) ?? throw new InvalidArgumentException("Unknown field type [{$field['type']}].");
            $options = is_array($field['options'] ?? null) ? array_values(array_filter($field['options'], 'is_string')) : [];
            $help = $field['help'] ?? null;

            $fields[] = new FormField($field['key'], $field['label'], $type, (bool) ($field['required'] ?? false), is_string($help) && $help !== '' ? $help : null, $options);
        }

        return new self($fields);
    }

    /**
     * @return list<array{key: string, label: string, type: string, required: bool, help: ?string, options: list<string>}>
     */
    public function toArray(): array
    {
        return array_map(fn (FormField $field): array => $field->toArray(), $this->fields);
    }

    /**
     * Validates answers and returns them with the field labels and types of this
     * version of the form. Keys not in the form are dropped.
     *
     * @param  array<string, mixed>  $answers
     * @return list<array{key: string, label: string, type: string, value: string|int|float|bool|null}>
     *
     * @throws ValidationException keyed by field key
     */
    public function answer(array $answers, ValidationFactory $validation): array
    {
        $rules = [];
        $attributes = [];

        foreach ($this->fields as $field) {
            $rules[$field->key] = $field->rules();
            $attributes[$field->key] = $field->label;
        }

        $valid = $validation->make($answers, $rules, [], $attributes)->validate();

        return array_map(fn (FormField $field): array => [
            'key' => $field->key,
            'label' => $field->label,
            'type' => $field->type->value,
            'value' => $this->normalize($field, $valid[$field->key] ?? null),
        ], $this->fields);
    }

    private function normalize(FormField $field, mixed $value): string|int|float|bool|null
    {
        if ($value === null || $value === '') {
            return $field->type === FieldType::Checkbox ? false : null;
        }

        return match ($field->type) {
            FieldType::Checkbox => filter_var($value, FILTER_VALIDATE_BOOL),
            FieldType::Number => is_numeric($value) ? $value + 0 : null,
            default => is_scalar($value) ? trim((string) $value) : null,
        };
    }
}
