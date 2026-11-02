<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Resources\SsoGroupMappings;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Sso\SsoGroupMapping;
use Modules\Core\Domain\Sso\SsoProvider;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Filament\Resources\SsoGroupMappings\Pages\ManageSsoGroupMappings;

/**
 * Maps groups of the organization's identity providers to roles. Records are
 * scoped by the organization scope; authorization comes from SsoPolicy.
 */
final class SsoGroupMappingResource extends Resource
{
    protected static ?string $model = SsoGroupMapping::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'group';

    public static function getModelLabel(): string
    {
        return __('core::sso.mapping.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::sso.mapping.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('core::directory.navigation_group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('provider_id')->label(__('core::sso.mapping.provider'))
                ->options(fn (): array => self::providers())
                ->in(fn (): array => array_keys(self::providers()))
                ->required(),
            TextInput::make('group')->label(__('core::sso.mapping.group'))->helperText(__('core::sso.mapping.group_help'))
                ->required()->maxLength(500)->dehydrateStateUsing(fn (string $state): string => trim($state)),
            Select::make('role_id')->label(__('core::sso.mapping.role'))
                ->options(fn (): array => self::assignableRoles())
                ->in(fn (): array => array_keys(self::assignableRoles()))
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['role', 'provider']))
            ->columns([
                TextColumn::make('provider.name')->label(__('core::sso.mapping.provider'))->sortable(),
                TextColumn::make('group')->label(__('core::sso.mapping.group'))->searchable()->sortable(),
                TextColumn::make('role.name')->label(__('core::sso.mapping.role'))->badge(),
            ])
            ->defaultSort('group')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSsoGroupMappings::route('/')];
    }

    /**
     * @return array<string, string>
     */
    private static function providers(): array
    {
        /** @var array<string, string> $providers */
        $providers = SsoProvider::query()->orderBy('name')->pluck('name', 'id')->all();

        return $providers;
    }

    /**
     * Global roles and the current organization's own roles.
     *
     * @return array<string, string>
     */
    private static function assignableRoles(): array
    {
        /** @var array<string, string> $roles */
        $roles = Role::query()
            ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', app(OrganizationContext::class)->id()))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return $roles;
    }
}
