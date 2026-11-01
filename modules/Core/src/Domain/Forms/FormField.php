<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Forms;

use Illuminate\Validation\Rule;
use InvalidArgumentException;

final readonly class FormField
{
    public const int MAX_TEXT = 255;

    public const int MAX_TEXTAREA = 5000;

    /**
     * @param  list<string>  $options  Choices of a select field.
     */
    public function __construct(
        public string $key,
        public string $label,
        public FieldType $type,
        public bool $required = false,
        public ?string $help = null,
        public array $options = [],
    ) {
        if (preg_match('/\A[a-z][a-z0-9_]{0,49}\z/', $key) !== 1) {
            throw new InvalidArgumentException("Field key [{$key}] must be snake_case, starting with a letter, at most 50 characters.");
        }

        if (trim($label) === '' || mb_strlen($label) > 200) {
            throw new InvalidArgumentException("Field [{$key}] needs a label of at most 200 characters.");
        }

        if ($type === FieldType::Select && ($options === [] || count($options) !== count(array_unique($options)))) {
            throw new InvalidArgumentException("Select field [{$key}] needs distinct options.");
        }

        if ($type !== FieldType::Select && $options !== []) {
            throw new InvalidArgumentException("Only select fields have options ([{$key}]).");
        }
    }

    /**
     * Laravel validation rules for an answer to this field.
     *
     * @return list<mixed>
     */
    public function rules(): array
    {
        $presence = $this->type === FieldType::Checkbox
            ? ($this->required ? ['accepted'] : ['nullable'])
            : ($this->required ? ['required'] : ['nullable']);

        return [...$presence, ...match ($this->type) {
            FieldType::Text => ['string', 'max:'.self::MAX_TEXT],
            FieldType::Textarea => ['string', 'max:'.self::MAX_TEXTAREA],
            FieldType::Number => ['numeric', 'between:-1000000000,1000000000'],
            FieldType::Date => ['date_format:Y-m-d'],
            FieldType::Select => ['string', Rule::in($this->options)],
            FieldType::Checkbox => ['boolean'],
        }];
    }

    /**
     * @return array{key: string, label: string, type: string, required: bool, help: ?string, options: list<string>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type->value,
            'required' => $this->required,
            'help' => $this->help,
            'options' => $this->options,
        ];
    }
}
