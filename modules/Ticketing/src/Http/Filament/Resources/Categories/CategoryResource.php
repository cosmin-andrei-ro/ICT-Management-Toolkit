<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Filament\Resources\Categories;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Ticketing\Domain\Models\Category;
use Modules\Ticketing\Http\Filament\Resources\Categories\Pages\ManageCategories;

/**
 * Two levels: a category may have a parent, which itself has none.
 */
final class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $slug = 'ticketing/categories';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('ticketing::admin.categories.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ticketing::admin.categories.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('ticketing::admin.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('ticketing::admin.name'))->required()->maxLength(100),
            Select::make('parent_id')
                ->label(__('ticketing::admin.categories.parent'))
                ->options(fn (?Category $record): array => self::parentOptions($record))
                ->in(fn (?Category $record): array => array_keys(self::parentOptions($record))),
            Textarea::make('description')->label(__('ticketing::admin.description'))->maxLength(1000)->columnSpanFull(),
            TextInput::make('sort_order')->label(__('ticketing::admin.sort_order'))->integer()->minValue(0)->maxValue(10000)->default(0),
            Toggle::make('is_active')->label(__('ticketing::admin.active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('parent'))
            ->columns([
                TextColumn::make('name')->label(__('ticketing::admin.name'))
                    ->formatStateUsing(fn (Category $record): string => $record->fullName())
                    ->searchable()->sortable(),
                IconColumn::make('is_active')->label(__('ticketing::admin.active'))->boolean(),
                TextColumn::make('sort_order')->label(__('ticketing::admin.sort_order'))->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCategories::route('/')];
    }

    /**
     * Top-level categories only (two levels), never the category itself.
     *
     * @return array<string, string>
     */
    private static function parentOptions(?Category $record): array
    {
        /** @var array<string, string> $options */
        $options = Category::query()
            ->whereNull('parent_id')
            ->when($record !== null, fn (Builder $query) => $query->whereKeyNot($record?->id))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return $options;
    }
}
