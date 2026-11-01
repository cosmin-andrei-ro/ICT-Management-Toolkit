<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Filament\Resources\Mailboxes;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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
use Illuminate\Support\Facades\RateLimiter;
use Modules\Ticketing\Application\Email\MailboxReader;
use Modules\Ticketing\Application\Email\MailboxUnavailable;
use Modules\Ticketing\Application\Email\MailServerGuard;
use Modules\Ticketing\Domain\Email\MailEncryption;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Models\Mailbox;
use Modules\Ticketing\Domain\Models\Queue;
use Modules\Ticketing\Http\Filament\Resources\Mailboxes\Pages\ManageMailboxes;

/**
 * The password is write-only: never sent back to the browser, kept when left empty.
 */
final class MailboxResource extends Resource
{
    protected static ?string $model = Mailbox::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $slug = 'ticketing/mailboxes';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('ticketing::email.admin.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ticketing::email.admin.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('ticketing::admin.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('ticketing::admin.name'))->required()->maxLength(100),
            TextInput::make('address')->label(__('ticketing::email.admin.address'))->helperText(__('ticketing::email.admin.address_help'))->email()->required()->maxLength(320),
            TextInput::make('host')->label(__('ticketing::email.admin.host'))->required()->maxLength(253)
                ->regex('/^(\[[0-9a-fA-F:.]+\]|[a-zA-Z0-9]([a-zA-Z0-9.-]{0,251}[a-zA-Z0-9])?)$/'),
            TextInput::make('port')->label(__('ticketing::email.admin.port'))->helperText(fn () => __('ticketing::email.admin.port_help', ['ports' => implode(', ', app(MailServerGuard::class)->allowedPorts())]))
                ->integer()->required()->default(993)->in(fn (): array => app(MailServerGuard::class)->allowedPorts()),
            Select::make('encryption')->label(__('ticketing::email.admin.encryption'))
                ->options(fn (): array => self::encryptionOptions())
                ->in(fn (): array => array_keys(self::encryptionOptions()))
                ->required()->default(MailEncryption::Ssl->value),
            TextInput::make('username')->label(__('ticketing::email.admin.username'))->required()->maxLength(255)->autocomplete('off'),
            TextInput::make('password')->label(__('ticketing::email.admin.password'))
                ->helperText(fn (string $operation) => $operation === 'edit' ? __('ticketing::email.admin.password_keep') : null)
                ->password()->revealable(false)->autocomplete('new-password')->maxLength(1000)
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state)),
            TextInput::make('folder')->label(__('ticketing::email.admin.folder'))->required()->default('INBOX')->maxLength(255),
            TextInput::make('processed_folder')->label(__('ticketing::email.admin.processed_folder'))->helperText(__('ticketing::email.admin.processed_folder_help'))->maxLength(255),
            Select::make('ticket_type')->label(__('ticketing::email.admin.ticket_type'))
                ->options([TicketType::Incident->value => TicketType::Incident->label(), TicketType::ServiceRequest->value => TicketType::ServiceRequest->label()])
                ->required()->default(TicketType::Incident->value),
            Select::make('queue_id')->label(__('ticketing::email.admin.queue'))->helperText(__('ticketing::email.admin.queue_help'))
                ->options(fn (): array => self::queueOptions())
                ->in(fn (): array => array_keys(self::queueOptions())),
            Toggle::make('is_active')->label(__('ticketing::admin.active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('ticketing::admin.name'))->searchable()->sortable(),
                TextColumn::make('address')->label(__('ticketing::email.admin.address'))->searchable(),
                IconColumn::make('is_active')->label(__('ticketing::admin.active'))->boolean(),
                TextColumn::make('last_checked_at')->label(__('ticketing::email.admin.last_checked'))->since(),
                TextColumn::make('last_error')->label(__('ticketing::email.admin.status'))
                    ->formatStateUsing(fn (?string $state): string => __('ticketing::email.reasons.'.$state))
                    ->placeholder(__('ticketing::email.admin.ok'))
                    ->color('danger'),
            ])
            ->defaultSort('name')
            ->recordActions([
                Action::make('test')
                    ->label(__('ticketing::email.admin.test'))
                    ->icon(Heroicon::OutlinedSignal)
                    ->authorize(fn (Mailbox $record): bool => Auth::user()?->can('update', $record) ?? false)
                    ->action(fn (Mailbox $record) => self::testConnection($record)),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageMailboxes::route('/')];
    }

    private static function testConnection(Mailbox $mailbox): void
    {
        // Each test opens a connection to a server of the administrator's choosing.
        $allowed = RateLimiter::attempt('mailbox-test:'.Auth::id(), 5, fn (): bool => true, 60);

        if ($allowed === false) {
            Notification::make()->title(__('ticketing::email.admin.test_throttled'))->danger()->send();

            return;
        }

        try {
            app(MailboxReader::class)->test($mailbox);
            Notification::make()->title(__('ticketing::email.admin.test_ok'))->success()->send();
        } catch (MailboxUnavailable $exception) {
            Notification::make()->title(__('ticketing::email.admin.test_failed'))->body(__('ticketing::email.reasons.'.$exception->reason))->danger()->send();
        }
    }

    /**
     * @return array<string, string>
     */
    private static function encryptionOptions(): array
    {
        $options = [];

        foreach (MailEncryption::cases() as $encryption) {
            if ($encryption !== MailEncryption::None || config('ticketing.email.allow_unencrypted') === true) {
                $options[$encryption->value] = $encryption->label();
            }
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private static function queueOptions(): array
    {
        /** @var array<string, string> $options */
        $options = Queue::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();

        return $options;
    }
}
