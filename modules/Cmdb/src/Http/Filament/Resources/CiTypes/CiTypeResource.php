<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Http\Filament\Resources\CiTypes;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Http\Filament\Resources\CiTypes\Pages\ManageCiTypes;
use Modules\Core\Http\Filament\Components\FormSchemaEditor;

/**
 * Item types and their attributes. Keys are fixed once created (imports use
 * them); types in use are deactivated rather than deleted.
 */
final class CiTypeResource extends Resource
{
    protected static ?string $model = CiType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $slug = 'cmdb/types';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('cmdb::types.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('cmdb::types.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('cmdb::types.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('cmdb::types.name'))->required()->maxLength(100),
            TextInput::make('key')->label(__('cmdb::types.key'))->helperText(__('cmdb::types.key_help'))
                ->required()->regex('/\A[a-z][a-z0-9_]{0,63}\z/')
                ->disabledOn('edit'),
            Textarea::make('description')->label(__('cmdb::types.description'))->maxLength(1000)->columnSpanFull(),
            Toggle::make('is_active')->label(__('cmdb::types.active'))->helperText(__('cmdb::types.active_help'))->default(true),
            Section::make(__('cmdb::types.attributes'))
                ->description(__('cmdb::types.attributes_help'))
                ->schema([FormSchemaEditor::make('attribute_schema')])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('items'))
            ->columns([
                TextColumn::make('name')->label(__('cmdb::types.name'))->searchable()->sortable(),
                TextColumn::make('key')->label(__('cmdb::types.key'))->fontFamily('mono'),
                TextColumn::make('items_count')->label(__('cmdb::types.items')),
                IconColumn::make('is_active')->label(__('cmdb::types.active'))->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (CiType $record): bool => ! $record->items()->withTrashed()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCiTypes::route('/')];
    }
}
