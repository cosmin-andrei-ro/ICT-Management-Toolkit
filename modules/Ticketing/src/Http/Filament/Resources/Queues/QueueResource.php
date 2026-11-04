<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Filament\Resources\Queues;

use BackedEnum;
use Filament\Actions\DeleteAction;
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
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Domain\Models\Queue;
use Modules\Ticketing\Http\Filament\Resources\Queues\Pages\ManageQueues;

final class QueueResource extends Resource
{
    protected static ?string $model = Queue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?string $slug = 'ticketing/queues';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('ticketing::admin.queues.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ticketing::admin.queues.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('ticketing::admin.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('ticketing::admin.name'))->required()->maxLength(100),
            Select::make('team_id')
                ->label(__('ticketing::admin.queues.team'))
                ->helperText(__('ticketing::admin.queues.team_help'))
                ->options(fn (): array => self::teamOptions())
                ->in(fn (): array => array_keys(self::teamOptions()))
                ->required(),
            Textarea::make('description')->label(__('ticketing::admin.description'))->maxLength(1000)->columnSpanFull(),
            Select::make('escalation_user_id')
                ->label(__('ticketing::admin.queues.escalation_user'))
                ->helperText(__('ticketing::admin.queues.escalation_user_help'))
                ->options(fn (): array => self::escalationOptions())
                ->in(fn (): array => array_keys(self::escalationOptions()))
                ->searchable(),
            TextInput::make('escalate_after_minutes')
                ->label(__('ticketing::admin.queues.escalate_after'))
                ->integer()->minValue(1)->maxValue(525600)->default(60)->required()
                ->suffix(__('ticketing::sla.minutes_suffix')),
            Toggle::make('is_default')->label(__('ticketing::admin.queues.default'))->helperText(__('ticketing::admin.queues.default_help')),
            Toggle::make('is_active')->label(__('ticketing::admin.active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('team'))
            ->columns([
                TextColumn::make('name')->label(__('ticketing::admin.name'))->searchable()->sortable(),
                TextColumn::make('team.name')->label(__('ticketing::admin.queues.team')),
                IconColumn::make('is_default')->label(__('ticketing::admin.queues.default'))->boolean(),
                IconColumn::make('is_active')->label(__('ticketing::admin.active'))->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageQueues::route('/')];
    }

    /**
     * @return array<string, string>
     */
    private static function escalationOptions(): array
    {
        /** @var array<string, string> $options */
        $options = User::query()->whereHas('organizations', fn (Builder $query) => $query->whereKey(app(OrganizationContext::class)->requireId()))
            ->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private static function teamOptions(): array
    {
        /** @var array<string, string> $options */
        $options = Team::query()->orderBy('name')->pluck('name', 'id')->all();

        return $options;
    }
}
