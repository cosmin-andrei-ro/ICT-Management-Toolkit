<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Resources\SsoProviders;

use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Modules\Core\Domain\Sso\SsoProtocol;
use Modules\Core\Domain\Sso\SsoProvider;
use Modules\Core\Http\Filament\Resources\SsoProviders\Pages\ManageSsoProviders;

/**
 * The organization's identity providers, OpenID Connect (ADR 0038) or SAML
 * 2.0 (ADR 0039). The OIDC client secret is write-only: never sent back to
 * the browser, kept when left empty.
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
        $oidc = fn (Get $get): bool => $get('protocol') !== SsoProtocol::Saml->value;
        $saml = fn (Get $get): bool => $get('protocol') === SsoProtocol::Saml->value;

        return $schema->components([
            Select::make('protocol')->label(__('core::sso.provider.protocol'))
                ->options(collect(SsoProtocol::cases())->mapWithKeys(fn (SsoProtocol $protocol): array => [$protocol->value => $protocol->label()])->all())
                ->required()->default(SsoProtocol::Oidc->value)->live()->disabledOn('edit'),
            TextInput::make('name')->label(__('core::sso.provider.name'))->helperText(__('core::sso.provider.name_help'))->required()->maxLength(100),
            TextInput::make('issuer')->label(fn (Get $get): string => __($saml($get) ? 'core::sso.provider.entity_id' : 'core::sso.provider.issuer'))
                ->helperText(fn (Get $get): string => __($saml($get) ? 'core::sso.provider.entity_id_help' : 'core::sso.provider.issuer_help'))
                ->required()->maxLength(500)
                // An OIDC issuer is an address; a SAML entity ID may be a URN.
                ->url($oidc),
            TextInput::make('client_id')->label(__('core::sso.provider.client_id'))->visible($oidc)->required($oidc)->maxLength(255)->autocomplete('off'),
            TextInput::make('client_secret')->label(__('core::sso.provider.client_secret'))->visible($oidc)
                ->helperText(fn (string $operation) => $operation === 'edit' ? __('core::sso.provider.client_secret_keep') : null)
                ->password()->revealable(false)->autocomplete('new-password')->maxLength(2000)
                ->required(fn (string $operation, Get $get): bool => $operation === 'create' && $oidc($get))
                ->dehydrated(fn (?string $state): bool => filled($state)),
            TextInput::make('sso_url')->label(__('core::sso.provider.sso_url'))->helperText(__('core::sso.provider.sso_url_help'))
                ->visible($saml)->required($saml)->maxLength(500)->url()->startsWith(['https://']),
            TextInput::make('slo_url')->label(__('core::sso.provider.slo_url'))->helperText(__('core::sso.provider.slo_url_help'))
                ->visible($saml)->maxLength(500)->url()->startsWith(['https://']),
            Toggle::make('want_encrypted_assertions')->label(__('core::sso.provider.want_encrypted'))->helperText(__('core::sso.provider.want_encrypted_help'))
                ->visible($saml)->default(false),
            Textarea::make('certificate')->label(__('core::sso.provider.certificate'))->helperText(__('core::sso.provider.certificate_help'))
                ->visible($saml)->required($saml)->rows(6)->maxLength(20000)
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    $pem = is_string($value) ? $value : '';
                    $pem = str_contains($pem, 'BEGIN CERTIFICATE') ? $pem : "-----BEGIN CERTIFICATE-----\n".trim($pem)."\n-----END CERTIFICATE-----";

                    if (openssl_x509_read($pem) === false) {
                        $fail(__('core::sso.provider.certificate_invalid'));
                    }
                }),
            TagsInput::make('allowed_domains')->label(__('core::sso.provider.allowed_domains'))->helperText(__('core::sso.provider.allowed_domains_help'))
                ->required()->nestedRecursiveRules(['regex:/\A[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+\z/i', 'max:253'])
                ->dehydrateStateUsing(fn (?array $state): array => array_values(array_unique(array_map(fn (mixed $domain): string => Str::lower(trim(is_string($domain) ? $domain : '')), $state ?? [])))),
            TextInput::make('scopes')->label(__('core::sso.provider.scopes'))->visible($oidc)->required($oidc)->maxLength(255)->default('openid email profile')
                ->rule('regex:/(^|\s)openid(\s|$)/'),
            TextInput::make('email_attribute')->label(__('core::sso.provider.email_attribute'))->helperText(__('core::sso.provider.email_attribute_help'))->visible($saml)->maxLength(255),
            TextInput::make('name_attribute')->label(__('core::sso.provider.name_attribute'))->visible($saml)->maxLength(255),
            TextInput::make('groups_claim')->label(fn (Get $get): string => __($saml($get) ? 'core::sso.provider.groups_attribute' : 'core::sso.provider.groups_claim'))
                ->helperText(__('core::sso.provider.groups_claim_help'))->required()->maxLength(255)->default('groups'),
            Toggle::make('trust_mfa')->label(__('core::sso.provider.trust_mfa'))->helperText(__('core::sso.provider.trust_mfa_help'))->default(true),
            Toggle::make('is_enabled')->label(__('core::sso.provider.is_enabled'))->default(true),
            TextInput::make('redirect_uri')->label(__('core::sso.provider.redirect_uri'))->helperText(__('core::sso.provider.redirect_uri_help'))
                ->visible(fn (string $operation, Get $get): bool => $operation === 'edit' && $oidc($get))->disabled()->dehydrated(false)
                ->formatStateUsing(fn (?SsoProvider $record): ?string => $record !== null ? route('login.sso.callback', $record->id) : null),
            TextInput::make('sp_metadata')->label(__('core::sso.provider.sp_metadata'))->helperText(__('core::sso.provider.sp_metadata_help'))
                ->visible(fn (string $operation, Get $get): bool => $operation === 'edit' && $saml($get))->disabled()->dehydrated(false)
                ->formatStateUsing(fn (?SsoProvider $record): ?string => $record !== null ? route('login.saml.metadata', $record->id) : null),
            TextInput::make('sp_acs')->label(__('core::sso.provider.sp_acs'))
                ->visible(fn (string $operation, Get $get): bool => $operation === 'edit' && $saml($get))->disabled()->dehydrated(false)
                ->formatStateUsing(fn (?SsoProvider $record): ?string => $record !== null ? route('login.saml.acs', $record->id) : null),
            TextInput::make('sp_slo')->label(__('core::sso.provider.sp_slo'))
                ->visible(fn (string $operation, Get $get): bool => $operation === 'edit' && $saml($get))->disabled()->dehydrated(false)
                ->formatStateUsing(fn (?SsoProvider $record): ?string => $record !== null ? route('login.saml.slo', $record->id) : null),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('core::sso.provider.name'))->searchable()->sortable(),
                TextColumn::make('protocol')->label(__('core::sso.provider.protocol'))->formatStateUsing(fn (SsoProtocol $state): string => $state->label())->badge(),
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
