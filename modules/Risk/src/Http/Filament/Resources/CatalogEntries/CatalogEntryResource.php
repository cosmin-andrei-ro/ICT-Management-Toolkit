<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Http\Filament\Resources\CatalogEntries;

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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Risk\Domain\Enums\CatalogKind;
use Modules\Risk\Domain\Models\CatalogEntry;
use Modules\Risk\Http\Filament\Resources\CatalogEntries\Pages\ManageCatalogEntries;

/**
 * Threats and vulnerabilities risks are built from. An entry in use is
 * deactivated rather than deleted, so risks keep it.
 */
final class CatalogEntryResource extends Resource
{
    protected static ?string $model = CatalogEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $slug = 'risk/catalog';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('risk::settings.catalog.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('risk::settings.catalog.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('risk::settings.navigation_group');
    }

    /**
     * @return array<string, string>
     */
    private static function kinds(): array
    {
        $kinds = [];

        foreach (CatalogKind::cases() as $kind) {
            $kinds[$kind->value] = $kind->label();
        }

        return $kinds;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('kind')->label(__('risk::settings.catalog.kind'))->options(self::kinds())->required()->disabledOn('edit'),
            TextInput::make('name')->label(__('risk::settings.catalog.name'))->required()->maxLength(255)
                ->rules([fn (Get $get, ?CatalogEntry $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                    $taken = is_string($value) && CatalogEntry::query()
                        ->where('kind', $record !== null ? $record->kind->value : $get('kind'))
                        ->whereRaw('lower(name) = ?', [mb_strtolower(trim($value))])
                        ->when($record !== null, fn ($query) => $query->whereKeyNot($record?->id))
                        ->exists();

                    if ($taken) {
                        $fail(__('risk::settings.catalog.taken'));
                    }
                }]),
            Textarea::make('description')->label(__('risk::settings.catalog.description'))->maxLength(2000)->columnSpanFull(),
            Toggle::make('is_active')->label(__('risk::settings.catalog.active'))->helperText(__('risk::settings.catalog.active_help'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kind')->label(__('risk::settings.catalog.kind'))->formatStateUsing(fn (CatalogKind $state): string => $state->label())->sortable(),
                TextColumn::make('name')->label(__('risk::settings.catalog.name'))->searchable()->sortable(),
                IconColumn::make('is_active')->label(__('risk::settings.catalog.active'))->boolean(),
            ])
            ->defaultSort('name')
            ->filters([SelectFilter::make('kind')->label(__('risk::settings.catalog.kind'))->options(self::kinds())])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (CatalogEntry $record): bool => ! $record->isUsed()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCatalogEntries::route('/')];
    }
}
