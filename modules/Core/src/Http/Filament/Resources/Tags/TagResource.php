<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Resources\Tags;

use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Domain\Models\Tag;
use Modules\Core\Domain\Models\User;
use Modules\Core\Http\Filament\Resources\Tags\Pages\ManageTagList;

/**
 * Tidying up the organization's tags: rename a misspelt one, delete one nobody
 * needs. They are made where they are used, on record pages.
 */
final class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('core.tag.manage');
    }

    public static function getModelLabel(): string
    {
        return __('core::tags.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::tags.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('core::locations.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('core::tags.name'))->required()->maxLength(Tag::MAX_LENGTH)
                ->dehydrateStateUsing(fn (string $state): string => Tag::normalize($state))
                ->rules([fn (?Tag $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                    $name = Tag::normalize(is_string($value) ? $value : '');

                    if (! Tag::isValidName($name)) {
                        $fail(__('core::tags.errors.invalid'));
                    } elseif (Tag::query()->where('name', $name)->when($record !== null, fn (Builder $query) => $query->whereKeyNot($record?->id))->exists()) {
                        $fail(__('core::tags.errors.taken'));
                    }
                }]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->selectRaw('core_tags.*, (select count(*) from core_taggables where core_taggables.tag_id = core_tags.id) as uses'))
            ->columns([
                TextColumn::make('name')->label(__('core::tags.name'))->searchable()->sortable(),
                TextColumn::make('uses')->label(__('core::tags.uses')),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTagList::route('/')];
    }
}
