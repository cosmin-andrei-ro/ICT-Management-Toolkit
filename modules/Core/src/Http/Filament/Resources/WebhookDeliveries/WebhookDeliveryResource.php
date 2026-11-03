<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Resources\WebhookDeliveries;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Application\Webhooks\DeliverWebhook;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Models\Webhook;
use Modules\Core\Domain\Models\WebhookDelivery;
use Modules\Core\Http\Filament\Resources\WebhookDeliveries\Pages\ListWebhookDeliveries;

/**
 * Every event sent, or being sent, and how the receiver answered (ADR 0066).
 */
final class WebhookDeliveryResource extends Resource
{
    protected static ?string $model = WebhookDelivery::class;

    protected static ?string $slug = 'webhook-deliveries';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    public static function getNavigationGroup(): string
    {
        return __('core::webhooks.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('core::webhooks.deliveries');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('core.webhooks.manage');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('webhook:id,name'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('core::webhooks.fields.when'))->dateTime(),
                TextColumn::make('webhook.name')->label(__('core::webhooks.model')),
                TextColumn::make('event')->label(__('core::webhooks.fields.event')),
                TextColumn::make('status')->label(__('core::webhooks.fields.status'))->badge()
                    ->formatStateUsing(fn (string $state): string => __('core::webhooks.statuses.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        'delivered' => 'success',
                        'failed' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('attempts')->label(__('core::webhooks.fields.attempts')),
                TextColumn::make('response_status')->label(__('core::webhooks.fields.response'))->placeholder('—'),
                TextColumn::make('error')->label(__('core::webhooks.fields.error'))->limit(60)->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('webhook_id')->label(__('core::webhooks.model'))->options(fn (): array => Webhook::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('status')->label(__('core::webhooks.fields.status'))->options(['pending' => __('core::webhooks.statuses.pending'), 'delivered' => __('core::webhooks.statuses.delivered'), 'failed' => __('core::webhooks.statuses.failed')]),
            ])
            ->recordActions([
                Action::make('retry')->label(__('core::webhooks.retry'))->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (WebhookDelivery $record): bool => $record->status === 'failed')
                    ->action(function (WebhookDelivery $record): void {
                        $record->forceFill(['status' => 'pending', 'attempts' => 0, 'next_attempt_at' => null])->save();
                        DeliverWebhook::dispatch($record->id);
                        Notification::make()->title(__('core::webhooks.retried'))->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListWebhookDeliveries::route('/')];
    }
}
