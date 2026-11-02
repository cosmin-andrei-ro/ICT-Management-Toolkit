<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Http\Filament\Resources\OptionValues;

use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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
use Illuminate\Database\Eloquent\Builder;
use Modules\Applications\Application\Options\DefaultOptions;
use Modules\Applications\Domain\Models\OptionValue;
use Modules\Applications\Http\Filament\Resources\OptionValues\Pages\ManageOptionValues;

/**
 * The values of the application sheet's dropdowns. Sheets keep the key, so a
 * value is renamed or deactivated, never deleted.
 */
final class OptionValueResource extends Resource
{
    protected static ?string $model = OptionValue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $slug = 'applications/dropdown-values';

    protected static ?string $recordTitleAttribute = 'label';

    public static function getModelLabel(): string
    {
        return __('applications::settings.option.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('applications::settings.option.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('applications::settings.navigation_group');
    }

    /**
     * @return array<string, string>
     */
    public static function lists(): array
    {
        $lists = [];

        foreach (array_keys(DefaultOptions::lists()) as $list) {
            $lists[$list] = __('applications::settings.lists.'.$list);
        }

        return $lists;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('list')->label(__('applications::settings.option.list'))->options(self::lists())->required()->disabledOn('edit'),
            TextInput::make('label')->label(__('applications::settings.option.value'))->required()->maxLength(255),
            TextInput::make('key')->label(__('applications::settings.option.key'))->helperText(__('applications::settings.option.key_help'))
                ->required()->maxLength(64)->regex('/^[a-z0-9][a-z0-9_]*$/')->disabledOn('edit')
                ->rules([fn (Get $get, ?OptionValue $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                    $taken = $record === null && is_string($value) && OptionValue::query()->where('list', $get('list'))->where('key', $value)->exists();

                    if ($taken) {
                        $fail(__('applications::settings.option.taken'));
                    }
                }]),
            TextInput::make('position')->label(__('applications::settings.option.position'))->integer()->minValue(0)->maxValue(1000)->default(100),
            Toggle::make('is_active')->label(__('applications::settings.option.active'))->helperText(__('applications::settings.option.active_help'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('list')->label(__('applications::settings.option.list'))->formatStateUsing(fn (string $state): string => self::lists()[$state] ?? $state)->sortable(),
                TextColumn::make('label')->label(__('applications::settings.option.value'))->searchable(),
                TextColumn::make('key')->label(__('applications::settings.option.key'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('position')->label(__('applications::settings.option.position'))->sortable(),
                IconColumn::make('is_active')->label(__('applications::settings.option.active'))->boolean(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('list')->orderBy('position'))
            ->filters([SelectFilter::make('list')->label(__('applications::settings.option.list'))->options(self::lists())])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageOptionValues::route('/')];
    }
}
