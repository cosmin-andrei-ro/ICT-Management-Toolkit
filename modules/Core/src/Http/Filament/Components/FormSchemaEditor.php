<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Components;

use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use InvalidArgumentException;
use Modules\Core\Domain\Forms\FieldType;
use Modules\Core\Domain\Forms\FormSchema;

/**
 * Admin editor for a Core FormSchema (ADR 0011): the questions of a service
 * request, the attributes of a CMDB item type…
 */
final class FormSchemaEditor
{
    public static function make(string $name): Repeater
    {
        $types = [];

        foreach (FieldType::cases() as $type) {
            $types[$type->value] = $type->label();
        }

        return Repeater::make($name)
            ->hiddenLabel()
            ->schema([
                TextInput::make('label')->label(__('core::forms.editor.label'))->required()->maxLength(200),
                TextInput::make('key')->label(__('core::forms.editor.key'))
                    ->helperText(__('core::forms.editor.key_help'))
                    ->required()->regex('/\A[a-z][a-z0-9_]{0,49}\z/'),
                Select::make('type')->label(__('core::forms.editor.type'))->options($types)->in(array_keys($types))->required()->live(),
                Toggle::make('required')->label(__('core::forms.editor.required')),
                TextInput::make('help')->label(__('core::forms.editor.help'))->maxLength(300),
                TagsInput::make('options')->label(__('core::forms.editor.options'))
                    ->visible(fn (Get $get): bool => $get('type') === FieldType::Select->value)
                    ->required(fn (Get $get): bool => $get('type') === FieldType::Select->value),
            ])
            ->columns(2)
            ->defaultItems(0)
            ->maxItems(FormSchema::MAX_FIELDS)
            // Stored as a JSON list; drop the repeater's item keys and non-select options.
            ->mutateDehydratedStateUsing(fn (?array $state): array => self::normalize($state ?? []))
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    FormSchema::fromArray(self::normalize(is_array($value) ? $value : []));
                } catch (InvalidArgumentException $exception) {
                    $fail($exception->getMessage());
                }
            });
    }

    /**
     * @param  array<mixed>  $fields
     * @return list<array<string, mixed>>
     */
    public static function normalize(array $fields): array
    {
        $normalized = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $isSelect = ($field['type'] ?? null) === FieldType::Select->value;
            $normalized[] = [
                'key' => $field['key'] ?? null,
                'label' => $field['label'] ?? null,
                'type' => $field['type'] ?? null,
                'required' => (bool) ($field['required'] ?? false),
                'help' => $field['help'] ?? null,
                'options' => $isSelect && is_array($field['options'] ?? null) ? array_values($field['options']) : [],
            ];
        }

        return $normalized;
    }
}
