<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Filament\Resources\InboundEmails;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Ticketing\Domain\Email\InboundOutcome;
use Modules\Ticketing\Domain\Models\InboundEmail;
use Modules\Ticketing\Http\Filament\Resources\InboundEmails\Pages\ListInboundEmails;

/**
 * Read-only: what happened to each message read from the mailboxes.
 */
final class InboundEmailResource extends Resource
{
    protected static ?string $model = InboundEmail::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $slug = 'ticketing/incoming-email';

    public static function getModelLabel(): string
    {
        return __('ticketing::email.log.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ticketing::email.log.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('ticketing::admin.navigation_group');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['mailbox', 'ticket']))
            ->columns([
                TextColumn::make('processed_at')->label(__('ticketing::email.log.processed_at'))->dateTime()->sortable(),
                TextColumn::make('mailbox.name')->label(__('ticketing::email.admin.label')),
                TextColumn::make('from_address')->label(__('ticketing::email.log.from'))->searchable(),
                TextColumn::make('subject')->label(__('ticketing::email.log.subject'))->limit(60)->searchable(),
                TextColumn::make('outcome')->label(__('ticketing::email.log.outcome'))->badge()
                    ->formatStateUsing(fn (InboundOutcome $state): string => $state->label())
                    ->color(fn (InboundOutcome $state): string => match (true) {
                        $state->reachedTicket() => 'success',
                        $state === InboundOutcome::Failed => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('ticket.number')->label(__('ticketing::email.log.ticket'))
                    ->url(fn (InboundEmail $record): ?string => $record->ticket !== null ? route('ticketing.desk.show', $record->ticket) : null),
            ])
            ->filters([
                SelectFilter::make('outcome')->label(__('ticketing::email.log.outcome'))
                    ->options(collect(InboundOutcome::cases())->mapWithKeys(fn (InboundOutcome $outcome): array => [$outcome->value => $outcome->label()])->all()),
            ])
            ->defaultSort('processed_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListInboundEmails::route('/')];
    }
}
