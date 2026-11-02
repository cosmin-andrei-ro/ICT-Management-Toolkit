<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Resources\Departments;

use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Domain\Models\Department;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Filament\Resources\Departments\Pages\ManageDepartments;

/**
 * The organization chart, as far as other modules need it: business processes
 * belong to a department. A department cannot move under itself or one of its
 * sub-departments, and cannot be deleted while it contains others.
 */
final class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('core::departments.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::departments.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('core::locations.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('core::departments.name'))->required()->maxLength(255)
                ->rules([fn (?Department $record): Closure => self::uniqueName($record)]),
            TextInput::make('code')->label(__('core::departments.code'))->maxLength(32),
            Select::make('parent_id')->label(__('core::departments.parent'))
                ->options(fn (?Department $record): array => self::parentOptions($record))
                ->in(fn (?Department $record): array => array_keys(self::parentOptions($record)))
                ->validationMessages(['in' => __('core::departments.invalid_parent')])
                ->searchable(),
            Select::make('manager_id')->label(__('core::departments.manager'))
                ->options(fn (): array => self::people())
                ->in(fn (): array => array_keys(self::people()))
                ->searchable(),
            Textarea::make('description')->label(__('core::departments.description'))->maxLength(2000)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['parent', 'manager']))
            ->columns([
                TextColumn::make('name')->label(__('core::departments.name'))->searchable()->sortable(),
                TextColumn::make('code')->label(__('core::departments.code')),
                TextColumn::make('parent.name')->label(__('core::departments.parent')),
                TextColumn::make('manager.name')->label(__('core::departments.manager')),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (Department $record): bool => ! $record->children()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDepartments::route('/')];
    }

    /**
     * @return array<string, string>
     */
    private static function parentOptions(?Department $record): array
    {
        $excluded = $record !== null && $record->exists ? $record->selfAndDescendantIds() : [];

        /** @var array<string, string> $options */
        $options = Department::query()->whereKeyNot($excluded)->orderBy('name')->pluck('name', 'id')->all();

        return $options;
    }

    /**
     * Active members of the current organization.
     *
     * @return array<string, string>
     */
    private static function people(): array
    {
        $organizationId = app(OrganizationContext::class)->id();

        /** @var array<string, string> $options */
        $options = User::query()
            ->where('is_active', true)
            ->whereHas('organizations', fn (Builder $query) => $query->whereKey($organizationId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return $options;
    }

    /**
     * Case-insensitive, like the database's unique index.
     */
    private static function uniqueName(?Department $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record): void {
            $taken = is_string($value) && Department::query()
                ->whereRaw('lower(name) = ?', [mb_strtolower(trim($value))])
                ->when($record !== null && $record->exists, fn ($query) => $query->whereKeyNot($record?->id))
                ->exists();

            if ($taken) {
                $fail(__('core::departments.taken'));
            }
        };
    }
}
