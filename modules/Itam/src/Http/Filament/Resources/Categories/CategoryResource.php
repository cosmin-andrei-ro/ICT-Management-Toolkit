<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Filament\Resources\Categories;

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
use Modules\Core\Http\Filament\Components\FormSchemaEditor;
use Modules\Itam\Domain\Models\Category;
use Modules\Itam\Http\Filament\Resources\Categories\Pages\ManageCategories;

/**
 * Asset categories and their attributes. Keys are fixed once created (imports use
 * them); categories in use are deactivated rather than deleted.
 */
final class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static ?string $slug = 'itam/categories';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('itam::categories.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('itam::categories.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('itam::categories.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('itam::categories.name'))->required()->maxLength(100),
            TextInput::make('key')->label(__('itam::categories.key'))->helperText(__('itam::categories.key_help'))
                ->required()->regex('/\A[a-z][a-z0-9_]{0,63}\z/')
                ->disabledOn('edit'),
            Textarea::make('description')->label(__('itam::categories.description'))->maxLength(1000)->columnSpanFull(),
            Toggle::make('is_active')->label(__('itam::categories.active'))->helperText(__('itam::categories.active_help'))->default(true),
            Section::make(__('itam::categories.attributes'))
                ->description(__('itam::categories.attributes_help'))
                ->schema([FormSchemaEditor::make('attribute_schema')])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('assets'))
            ->columns([
                TextColumn::make('name')->label(__('itam::categories.name'))->searchable()->sortable(),
                TextColumn::make('key')->label(__('itam::categories.key'))->fontFamily('mono'),
                TextColumn::make('assets_count')->label(__('itam::categories.assets')),
                IconColumn::make('is_active')->label(__('itam::categories.active'))->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (Category $record): bool => ! $record->assets()->withTrashed()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCategories::route('/')];
    }
}
