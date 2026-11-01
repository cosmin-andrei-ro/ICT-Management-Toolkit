<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Resources\Teams;

use BackedEnum;
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
use Modules\Core\Application\Teams\SyncTeamMembers;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Filament\Resources\Teams\Pages\ManageTeams;

/**
 * Members are written through SyncTeamMembers (organization check, audit), not
 * through the form's relationship.
 */
final class TeamResource extends Resource
{
    protected static ?string $model = Team::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('core::teams.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::teams.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('core::directory.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('core::teams.name'))->required()->maxLength(100),
            TextInput::make('email')->label(__('core::teams.email'))->email()->maxLength(255),
            Select::make('lead_id')
                ->label(__('core::teams.lead'))
                ->options(fn (): array => self::memberOptions())
                ->in(fn (): array => array_keys(self::memberOptions()))
                ->searchable(),
            Select::make('member_ids')
                ->label(__('core::teams.members'))
                ->multiple()
                ->options(fn (): array => self::memberOptions())
                ->in(fn (): array => array_keys(self::memberOptions()))
                ->searchable(),
            Textarea::make('description')->label(__('core::teams.description'))->maxLength(1000)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('lead')->withCount('members'))
            ->columns([
                TextColumn::make('name')->label(__('core::teams.name'))->searchable()->sortable(),
                TextColumn::make('lead.name')->label(__('core::teams.lead')),
                TextColumn::make('members_count')->label(__('core::teams.members')),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, Team $record): array {
                        $data['member_ids'] = $record->members()->pluck('users.id')->all();

                        return $data;
                    })
                    ->using(function (Team $record, array $data): Team {
                        $record->update(self::teamAttributes($data));
                        app(SyncTeamMembers::class)->handle($record, self::memberIds($data));

                        return $record;
                    }),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTeams::route('/')];
    }

    /**
     * Only the team's own fields, whatever else the form submitted.
     *
     * @param  array<mixed>  $data
     * @return array<string, mixed>
     */
    public static function teamAttributes(array $data): array
    {
        return array_intersect_key($data, array_flip(['name', 'email', 'lead_id', 'description']));
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
