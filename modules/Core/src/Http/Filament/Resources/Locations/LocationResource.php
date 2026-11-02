<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Resources\Locations;

use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Core\Domain\Enums\LocationKind;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Http\Filament\Resources\Locations\Pages\ManageLocations;

/**
 * Sites, buildings, floors and rooms. A location cannot move under itself or one
 * of its sub-locations, and cannot be deleted while it contains others.
 */
final class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('core::locations.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::locations.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('core::locations.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('core::locations.name'))->required()->maxLength(255)
                ->rules([fn (?Location $record, Get $get): Closure => self::uniqueInParent($record, $get('parent_id'))]),
            Select::make('kind')->label(__('core::locations.kind'))->required()
                ->options(collect(LocationKind::cases())->mapWithKeys(fn (LocationKind $kind): array => [$kind->value => $kind->label()])->all())
                ->default(LocationKind::Site->value),
            Select::make('parent_id')->label(__('core::locations.parent'))->helperText(__('core::locations.parent_help'))
                ->options(fn (?Location $record): array => self::parentOptions($record))
                ->in(fn (?Location $record): array => array_keys(self::parentOptions($record)))
                ->validationMessages(['in' => __('core::locations.invalid_parent')])
                ->searchable(),
            TextInput::make('code')->label(__('core::locations.code'))->maxLength(32),
            Textarea::make('address')->label(__('core::locations.address'))->maxLength(1000),
            Textarea::make('notes')->label(__('core::locations.notes'))->maxLength(2000),
            Toggle::make('is_active')->label(__('core::locations.active'))->helperText(__('core::locations.active_help'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('core::locations.path'))
                    ->formatStateUsing(fn (Location $record): string => once(fn (): array => Location::paths(false))[$record->id] ?? $record->name)
                    ->searchable()->sortable(),
                TextColumn::make('kind')->label(__('core::locations.kind'))->formatStateUsing(fn (LocationKind $state): string => $state->label()),
                TextColumn::make('code')->label(__('core::locations.code')),
                IconColumn::make('is_active')->label(__('core::locations.active'))->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (Location $record): bool => ! $record->children()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageLocations::route('/')];
    }

    /**
     * Locations this one may sit inside: any but itself and its own sub-locations.
     *
     * @return array<string, string>
     */
    public static function parentOptions(?Location $record): array
    {
        $excluded = $record !== null && $record->exists ? $record->selfAndDescendantIds() : [];

        return array_diff_key(Location::paths(), array_flip($excluded));
    }

    /**
     * Case-insensitive within the same parent, like the database's unique index.
     */
    private static function uniqueInParent(?Location $record, mixed $parentId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record, $parentId): void {
            $taken = is_string($value) && Location::query()
                ->whereRaw('lower(name) = ?', [mb_strtolower(trim($value))])
                ->when(is_string($parentId) && $parentId !== '', fn ($query) => $query->where('parent_id', $parentId), fn ($query) => $query->whereNull('parent_id'))
                ->when($record !== null && $record->exists, fn ($query) => $query->whereKeyNot($record?->id))
                ->exists();

            if ($taken) {
                $fail(__('core::locations.duplicate'));
            }
        };
    }
}
