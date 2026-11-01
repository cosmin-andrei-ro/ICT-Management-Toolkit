<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Filament\Resources\CabBoards;

use BackedEnum;
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
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Application\Changes\SyncCabMembers;
use Modules\Ticketing\Domain\Models\CabBoard;
use Modules\Ticketing\Http\Filament\Resources\CabBoards\Pages\ManageCabBoards;

/**
 * Members are written through SyncCabMembers (organization check, audit). Boards
 * are deactivated, never deleted, so changes waiting on them keep their approvers.
 */
final class CabBoardResource extends Resource
{
    protected static ?string $model = CabBoard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $slug = 'ticketing/cab-boards';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('ticketing::admin.cab_boards.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ticketing::admin.cab_boards.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('ticketing::admin.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('ticketing::admin.name'))->required()->maxLength(100),
            Select::make('member_ids')
                ->label(__('ticketing::admin.cab_boards.members'))
                ->helperText(__('ticketing::admin.cab_boards.members_help'))
                ->multiple()
                ->options(fn (): array => self::memberOptions())
                ->in(fn (): array => array_keys(self::memberOptions()))
                ->searchable(),
            Textarea::make('description')->label(__('ticketing::admin.description'))->maxLength(1000)->columnSpanFull(),
            Toggle::make('is_emergency')->label(__('ticketing::admin.cab_boards.emergency'))->helperText(__('ticketing::admin.cab_boards.emergency_help')),
            Toggle::make('is_active')->label(__('ticketing::admin.active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('members'))
            ->columns([
                TextColumn::make('name')->label(__('ticketing::admin.name'))->searchable()->sortable(),
                IconColumn::make('is_emergency')->label(__('ticketing::admin.cab_boards.emergency'))->boolean(),
                TextColumn::make('members_count')->label(__('ticketing::admin.cab_boards.members')),
                IconColumn::make('is_active')->label(__('ticketing::admin.active'))->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, CabBoard $record): array {
                        $data['member_ids'] = $record->members()->pluck('users.id')->all();

                        return $data;
                    })
                    ->using(function (CabBoard $record, array $data): CabBoard {
                        $record->update(self::boardAttributes($data));
                        app(SyncCabMembers::class)->handle($record, self::memberIds($data));

                        return $record;
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCabBoards::route('/')];
    }

    /**
     * Only the board's own fields, whatever else the form submitted.
     *
     * @param  array<mixed>  $data
     * @return array<string, mixed>
     */
    public static function boardAttributes(array $data): array
    {
        return array_intersect_key($data, array_flip(['name', 'description', 'is_emergency', 'is_active']));
    }

    /**
     * @param  array<mixed>  $data
     * @return list<string>
     */
    public static function memberIds(array $data): array
    {
        $members = $data['member_ids'] ?? [];

        return is_array($members) ? array_values(array_filter($members, 'is_string')) : [];
    }

    /**
     * Members of the current organization.
     *
     * @return array<string, string>
     */
    private static function memberOptions(): array
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
}
