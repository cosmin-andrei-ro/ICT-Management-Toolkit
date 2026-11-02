<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Filament\Resources\SoftwareProducts;

use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Itam\Domain\Models\SoftwareProduct;
use Modules\Itam\Http\Filament\Resources\SoftwareProducts\Pages\ManageSoftwareProducts;

/**
 * Software products, to rename them or record their publisher. Products are
 * created with their first licence; one with licences cannot be deleted.
 */
final class SoftwareProductResource extends Resource
{
    protected static ?string $model = SoftwareProduct::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $slug = 'itam/software-products';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('itam::products.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('itam::products.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('itam::categories.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('itam::products.name'))->required()->maxLength(255)
                ->rules([fn (?SoftwareProduct $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                    $taken = is_string($value) && SoftwareProduct::query()
                        ->whereRaw('lower(name) = ?', [mb_strtolower(trim($value))])
                        ->when($record !== null, fn ($query) => $query->whereKeyNot($record?->id))
                        ->exists();

                    if ($taken) {
                        $fail(__('itam::products.taken'));
                    }
                }]),
            TextInput::make('publisher')->label(__('itam::products.publisher'))->maxLength(255),
            Textarea::make('notes')->label(__('itam::products.notes'))->maxLength(2000)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('licenses'))
            ->columns([
                TextColumn::make('name')->label(__('itam::products.name'))->searchable()->sortable(),
                TextColumn::make('publisher')->label(__('itam::products.publisher'))->searchable(),
                TextColumn::make('licenses_count')->label(__('itam::products.licenses')),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (SoftwareProduct $record): bool => ! $record->licenses()->withTrashed()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSoftwareProducts::route('/')];
    }
}
