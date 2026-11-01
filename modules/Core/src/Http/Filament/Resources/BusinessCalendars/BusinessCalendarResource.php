<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Resources\BusinessCalendars;

use BackedEnum;
use Closure;
use DateTimeZone;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Core\Domain\Models\BusinessCalendar;
use Modules\Core\Http\Filament\Resources\BusinessCalendars\Pages\ManageBusinessCalendars;

/**
 * One working interval per weekday in the form (empty = closed). The model accepts
 * several per day; split days can be added here later without a data change.
 */
final class BusinessCalendarResource extends Resource
{
    private const array WEEKDAYS = [1 => 'monday', 2 => 'tuesday', 3 => 'wednesday', 4 => 'thursday', 5 => 'friday', 6 => 'saturday', 7 => 'sunday'];

    private const string TIME_PATTERN = '/\A([01]\d|2[0-3]):[0-5]\d\z/';

    protected static ?string $model = BusinessCalendar::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $slug = 'business-calendars';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('core::calendars.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::calendars.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('core::calendars.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        $days = [];
        foreach (self::WEEKDAYS as $key) {
            $days[] = Grid::make(2)->schema([
                TextInput::make("{$key}_start")->label(__("core::calendars.days.{$key}"))->placeholder('09:00')->regex(self::TIME_PATTERN)->requiredWith("{$key}_end"),
                TextInput::make("{$key}_end")->label(__('core::calendars.until'))->placeholder('17:00')->regex(self::TIME_PATTERN)->requiredWith("{$key}_start")
                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $key): void {
                        $start = $get("{$key}_start");

                        if (is_string($value) && $value !== '' && is_string($start) && $value <= $start) {
                            $fail(__('core::calendars.end_after_start'));
                        }
                    }),
            ]);
        }

        return $schema->components([
            TextInput::make('name')->label(__('core::calendars.name'))->required()->maxLength(100),
            Select::make('timezone')->label(__('core::calendars.timezone'))
                ->options(array_combine(DateTimeZone::listIdentifiers(), DateTimeZone::listIdentifiers()))
                ->in(DateTimeZone::listIdentifiers())
                ->searchable()->required()->default('Europe/Bucharest'),
            Select::make('public_holidays')->label(__('core::calendars.public_holidays'))
                ->options(['RO' => __('core::calendars.countries.RO')])
                ->in(['RO'])
                ->placeholder(__('core::calendars.none'))
                ->default('RO'),
            Toggle::make('is_default')->label(__('core::calendars.default'))->helperText(__('core::calendars.default_help')),
            Section::make(__('core::calendars.working_hours'))->description(__('core::calendars.working_hours_help'))->schema($days),
            Repeater::make('holidays')->label(__('core::calendars.extra_holidays'))
                ->relationship()
                ->schema([
                    DatePicker::make('date')->label(__('core::calendars.date'))->required(),
                    TextInput::make('name')->label(__('core::calendars.holiday_name'))->required()->maxLength(100),
                ])
                ->columns(2)
                ->defaultItems(0)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('core::calendars.name'))->searchable()->sortable(),
                TextColumn::make('timezone')->label(__('core::calendars.timezone')),
                IconColumn::make('is_default')->label(__('core::calendars.default'))->boolean(),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data): array => [...$data, ...self::toForm($data['weekly_hours'] ?? [])])
                    ->mutateDataUsing(fn (array $data): array => self::fromForm($data)),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBusinessCalendars::route('/')];
    }

    /**
     * @param  array<mixed>  $data
     * @return array<string, mixed>
     */
    public static function fromForm(array $data): array
    {
        $weekly = [];

        foreach (self::WEEKDAYS as $weekday => $key) {
            $start = $data["{$key}_start"] ?? null;
            $end = $data["{$key}_end"] ?? null;
            $weekly[$weekday] = is_string($start) && $start !== '' && is_string($end) && $end !== '' ? [[$start, $end]] : [];
            unset($data["{$key}_start"], $data["{$key}_end"]);
        }

        $attributes = array_intersect_key($data, array_flip(['name', 'timezone', 'public_holidays', 'is_default']));
        $attributes['weekly_hours'] = $weekly;

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    private static function toForm(mixed $weekly): array
    {
        $fields = [];

        foreach (self::WEEKDAYS as $weekday => $key) {
            $day = is_array($weekly) ? ($weekly[$weekday] ?? $weekly[(string) $weekday] ?? []) : [];
            $interval = is_array($day) ? ($day[0] ?? null) : null;
            $fields["{$key}_start"] = is_array($interval) && is_string($interval[0] ?? null) ? $interval[0] : '';
            $fields["{$key}_end"] = is_array($interval) && is_string($interval[1] ?? null) ? $interval[1] : '';
        }

        return $fields;
    }
}
