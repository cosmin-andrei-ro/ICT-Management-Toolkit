<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Http\Filament\Resources\ImpactCategories;

use BackedEnum;
use Closure;
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
use Modules\Bia\Domain\Models\ImpactCategory;
use Modules\Bia\Domain\Models\ImpactScore;
use Modules\Bia\Http\Filament\Resources\ImpactCategories\Pages\ManageImpactCategories;

/**
 * Kinds of impact and what each level 1–5 means. A category already scored is
 * deactivated rather than deleted, so approved assessments keep it.
 */
final class ImpactCategoryResource extends Resource
{
    protected static ?string $model = ImpactCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $slug = 'bia/impact-categories';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('bia::settings.category.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('bia::settings.category.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('bia::settings.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('bia::settings.category.name'))->required()->maxLength(255)
                ->rules([fn (?ImpactCategory $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                    $taken = is_string($value) && ImpactCategory::query()
                        ->whereRaw('lower(name) = ?', [mb_strtolower(trim($value))])
                        ->when($record !== null, fn ($query) => $query->whereKeyNot($record?->id))
                        ->exists();

                    if ($taken) {
                        $fail(__('bia::settings.category.taken'));
                    }
                }]),
            TextInput::make('sort_order')->label(__('bia::settings.category.sort_order'))->integer()->minValue(0)->maxValue(1000)->default(0),
            Textarea::make('description')->label(__('bia::settings.category.description'))->maxLength(1000)->columnSpanFull(),
            Section::make(__('bia::settings.category.levels'))->schema(array_map(
                fn (int $level): TextInput => TextInput::make("levels.{$level}")->label(__('bia::settings.category.level', ['level' => $level + 1]))->maxLength(255),
                range(0, 4),
            ))->columnSpanFull(),
            Toggle::make('is_active')->label(__('bia::settings.category.active'))->helperText(__('bia::settings.category.active_help'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('bia::settings.category.name'))->searchable(),
                TextColumn::make('sort_order')->label(__('bia::settings.category.sort_order'))->sortable(),
                IconColumn::make('is_active')->label(__('bia::settings.category.active'))->boolean(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make()->mutateDataUsing(fn (array $data): array => self::listLevels($data)),
                DeleteAction::make()->visible(fn (ImpactCategory $record): bool => ! ImpactScore::query()->where('category_id', $record->id)->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageImpactCategories::route('/')];
    }

    /**
     * The form keys level texts 0–4; the column holds them as a list.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public static function listLevels(array $data): array
    {
        $levels = is_array($data['levels'] ?? null) ? $data['levels'] : [];
        $data['levels'] = array_map(fn (int $level): string => is_string($levels[$level] ?? null) ? trim($levels[$level]) : '', range(0, 4));

        return $data;
    }
}
