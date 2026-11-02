<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Resources\SsoProviders;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Modules\Core\Domain\Sso\SsoProvider;
use Modules\Core\Http\Filament\Resources\SsoProviders\Pages\ManageSsoProviders;

/**
 * The organization's OpenID Connect identity providers (ADR 0038). The client
 * secret is write-only: never sent back to the browser, kept when left empty.
 */
final class SsoProviderResource extends Resource
{
    protected static ?string $model = SsoProvider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('core::sso.provider.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::sso.provider.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('core::directory.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('core::sso.provider.name'))->helperText(__('core::sso.provider.name_help'))->required()->maxLength(100),
            TextInput::make('issuer')->label(__('core::sso.provider.issuer'))->helperText(__('core::sso.provider.issuer_help'))
                ->required()->maxLength(500)->url()->startsWith(['https://', 'http://']),
            TextInput::make('client_id')->label(__('core::sso.provider.client_id'))->required()->maxLength(255)->autocomplete('off'),
            TextInput::make('client_secret')->label(__('core::sso.provider.client_secret'))
                ->helperText(fn (string $operation) => $operation === 'edit' ? __('core::sso.provider.client_secret_keep') : null)
                ->password()->revealable(false)->autocomplete('new-password')->maxLength(2000)
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state)),
            TagsInput::make('allowed_domains')->label(__('core::sso.provider.allowed_domains'))->helperText(__('core::sso.provider.allowed_domains_help'))
                ->required()->nestedRecursiveRules(['regex:/\A[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+\z/i', 'max:253'])
                ->dehydrateStateUsing(fn (?array $state): array => array_values(array_unique(array_map(fn (mixed $domain): string => Str::lower(trim(is_string($domain) ? $domain : '')), $state ?? [])))),
            TextInput::make('scopes')->label(__('core::sso.provider.scopes'))->required()->maxLength(255)->default('openid email profile')
                ->rule('regex:/(^|\s)openid(\s|$)/'),
            TextInput::make('groups_claim')->label(__('core::sso.provider.groups_claim'))->helperText(__('core::sso.provider.groups_claim_help'))->required()->maxLength(100)->default('groups'),
            Toggle::make('trust_mfa')->label(__('core::sso.provider.trust_mfa'))->helperText(__('core::sso.provider.trust_mfa_help'))->default(true),
            Toggle::make('is_enabled')->label(__('core::sso.provider.is_enabled'))->default(true),
            TextInput::make('redirect_uri')->label(__('core::sso.provider.redirect_uri'))->helperText(__('core::sso.provider.redirect_uri_help'))
                ->visibleOn('edit')->disabled()->dehydrated(false)
                ->formatStateUsing(fn (?SsoProvider $record): ?string => $record !== null ? route('login.sso.callback', $record->id) : null),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('core::sso.provider.name'))->searchable()->sortable(),
                TextColumn::make('issuer')->label(__('core::sso.provider.issuer')),
                TextColumn::make('allowed_domains')->label(__('core::sso.provider.allowed_domains'))->badge(),
                IconColumn::make('trust_mfa')->label(__('core::sso.provider.trust_mfa'))->boolean(),
                IconColumn::make('is_enabled')->label(__('core::sso.provider.is_enabled'))->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSsoProviders::route('/')];
    }
}
