<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Resources\Webhooks;

use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Modules\Core\Application\Webhooks\PublishWebhookEvent;
use Modules\Core\Application\Webhooks\WebhookUrlGuard;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Models\Webhook;
use Modules\Core\Domain\Webhooks\WebhookEvents;
use Modules\Core\Http\Filament\Resources\Webhooks\Pages\ManageWebhooks;

/**
 * Where the organization's events are sent (ADR 0066), for administrators.
 */
final class WebhookResource extends Resource
{
    protected static ?string $model = Webhook::class;

    protected static ?string $slug = 'webhooks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    public static function getNavigationGroup(): string
    {
        return __('core::webhooks.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('core::webhooks.title');
    }

    public static function getModelLabel(): string
    {
        return __('core::webhooks.model');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('core.webhooks.manage');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('core::webhooks.fields.name'))->required()->maxLength(255),
            TextInput::make('url')->label(__('core::webhooks.fields.url'))->helperText(__('core::webhooks.fields.url_help'))->required()->maxLength(2048)->url()
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! app(WebhookUrlGuard::class)->allows($value)) {
                        $fail(__('core::webhooks.errors.url'));
                    }
                }),
            CheckboxList::make('events')->label(__('core::webhooks.fields.events'))->required()->columns(2)
                ->options(fn (): array => array_map(fn (string $label): string => __($label), app(WebhookEvents::class)->all())),
            Toggle::make('is_active')->label(__('core::webhooks.fields.active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('core::webhooks.fields.name'))->searchable(),
                TextColumn::make('url')->label(__('core::webhooks.fields.url'))->limit(60),
                TextColumn::make('events')->label(__('core::webhooks.fields.events'))->badge(),
                IconColumn::make('is_active')->label(__('core::webhooks.fields.active'))->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('test')->label(__('core::webhooks.test'))->icon(Heroicon::OutlinedBolt)
                    ->action(function (Webhook $record): void {
                        app(PublishWebhookEvent::class)->handle('webhook.test', ['id' => $record->id, 'title' => __('core::webhooks.test_title')], only: $record);
                        Notification::make()->title(__('core::webhooks.test_sent'))->success()->send();
                    }),
                Action::make('rotate')->label(__('core::webhooks.rotate'))->icon(Heroicon::OutlinedArrowPath)->color('warning')
                    ->requiresConfirmation()->modalDescription(__('core::webhooks.rotate_help'))
                    ->action(function (Webhook $record): void {
                        $secret = self::newSecret();
                        $record->forceFill(['secret' => $secret])->save();
                        self::showSecret($secret);
                    }),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageWebhooks::route('/')];
    }

    public static function newSecret(): string
    {
        return 'whsec_'.Str::random(48);
    }

    /**
     * The secret is shown once, when created or rotated.
     */
    public static function showSecret(string $secret): void
    {
        Notification::make()->title(__('core::webhooks.secret_title'))->body(__('core::webhooks.secret_body', ['secret' => $secret]))->warning()->persistent()->send();
    }
}
