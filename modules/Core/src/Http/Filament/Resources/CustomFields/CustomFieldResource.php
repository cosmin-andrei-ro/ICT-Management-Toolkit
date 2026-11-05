<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Resources\CustomFields;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use Modules\Core\Domain\CustomFields\CustomFieldSchema;
use Modules\Core\Domain\CustomFields\CustomFieldTypes;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Filament\Components\FormSchemaEditor;
use Modules\Core\Http\Filament\Resources\CustomFields\Pages\ManageCustomFields;

/**
 * Which extra fields each kind of record has, per organization (ADR 0077).
 */
final class CustomFieldResource extends Resource
{
    protected static ?string $model = CustomFieldSchema::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('core.custom_fields.manage');
    }

    public static function getModelLabel(): string
    {
        return __('core::custom_fields.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::custom_fields.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('core::locations.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        $types = fn (): array => app(CustomFieldTypes::class)->all();

        return $schema->components([
            Select::make('entity_type')->label(__('core::custom_fields.entity_type'))
                ->options($types)
                ->in(fn (): array => array_keys($types()))
                ->required()
                ->disabledOn('edit')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('organization_id', app(OrganizationContext::class)->requireId())),
            Section::make(__('core::custom_fields.fields'))
                ->description(__('core::custom_fields.fields_help'))
                ->schema([FormSchemaEditor::make('schema')]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entity_type')->label(__('core::custom_fields.entity_type'))
                    ->formatStateUsing(fn (string $state): string => app(CustomFieldTypes::class)->all()[$state] ?? $state),
                TextColumn::make('fields')->label(__('core::custom_fields.fields'))
                    ->state(fn (CustomFieldSchema $record): int => count($record->schema)),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCustomFields::route('/')];
    }
}
