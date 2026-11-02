<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Http\Filament\Resources\TimeHorizons;

use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Bia\Domain\Models\TimeHorizon;
use Modules\Bia\Http\Filament\Resources\TimeHorizons\Pages\ManageTimeHorizons;

/**
 * Points in time at which impact is scored. Their duration is fixed once used,
 * since approved assessments were scored against it.
 */
final class TimeHorizonResource extends Resource
{
    protected static ?string $model = TimeHorizon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $slug = 'bia/time-horizons';

    protected static ?string $recordTitleAttribute = 'label';

    public static function getModelLabel(): string
    {
        return __('bia::settings.horizon.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('bia::settings.horizon.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('bia::settings.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')->label(__('bia::settings.horizon.label_field'))->required()->maxLength(64),
            TextInput::make('minutes')->label(__('bia::settings.horizon.minutes'))->helperText(__('bia::settings.horizon.minutes_help'))
                ->integer()->required()->minValue(1)->maxValue(525600)
                ->disabled(fn (?TimeHorizon $record): bool => $record?->isInUse() === true)
                ->rules([fn (?TimeHorizon $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                    $taken = is_numeric($value) && TimeHorizon::query()->where('minutes', (int) $value)
                        ->when($record !== null, fn ($query) => $query->whereKeyNot($record?->id))
                        ->exists();

                    if ($taken) {
                        $fail(__('bia::settings.horizon.taken'));
                    }
                }]),
            Toggle::make('is_active')->label(__('bia::settings.horizon.active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->label(__('bia::settings.horizon.label_field')),
                TextColumn::make('minutes')->label(__('bia::settings.horizon.minutes'))->sortable(),
                IconColumn::make('is_active')->label(__('bia::settings.horizon.active'))->boolean(),
            ])
            ->defaultSort('minutes')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (TimeHorizon $record): bool => ! $record->isInUse()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTimeHorizons::route('/')];
    }
}
