<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Resources\Users;

use BackedEnum;
use Filament\Actions\Action;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Modules\Core\Application\Authentication\AccountLockout;
use Modules\Core\Application\Authentication\TwoFactorAuthentication;
use Modules\Core\Application\Users\InviteUser;
use Modules\Core\Application\Users\RemoveFromOrganization;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Directory\AuthSource;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Filament\Resources\Users\Pages\ManageUsers;

/**
 * People of the current organization. Roles of AD accounts come from the AD
 * group mappings and are read-only here.
 */
final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static ?string $slug = 'people';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('core::users.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::users.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('core::directory.navigation_group');
    }

    public static function getEloquentQuery(): Builder
    {
        $organizationId = app(OrganizationContext::class)->id();

        return parent::getEloquentQuery()->whereHas('organizations', fn (Builder $query) => $query->whereKey($organizationId));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('core::users.name'))->required()->maxLength(200)
                ->disabled(fn (?User $record): bool => $record?->isDirectoryAccount() ?? false),
            Select::make('role_ids')->label(__('core::users.roles'))->multiple()
                ->options(fn (): array => self::roleOptions())
                ->in(fn (): array => array_keys(self::roleOptions()))
                ->helperText(fn (?User $record) => $record?->isDirectoryAccount() === true ? __('core::users.roles_from_directory') : null)
                ->disabled(fn (?User $record): bool => $record !== null && ($record->isDirectoryAccount() || ! (Auth::user()?->can('manageAccess', $record) ?? false))),
            Toggle::make('is_active')->label(__('core::users.active'))->helperText(__('core::users.active_help'))
                ->disabled(fn (?User $record): bool => $record === null || ! (Auth::user()?->can('deactivate', $record) ?? false)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('roles'))
            ->columns([
                TextColumn::make('name')->label(__('core::users.name'))->searchable()->sortable(),
                TextColumn::make('email')->label(__('core::users.email'))->searchable(),
                TextColumn::make('auth_source')->label(__('core::users.source'))
                    ->formatStateUsing(fn (AuthSource $state): string => __('core::users.sources.'.$state->value)),
                TextColumn::make('roles.name')->label(__('core::users.roles'))->badge(),
                IconColumn::make('two_factor_confirmed_at')->label(__('core::users.mfa'))->boolean()->getStateUsing(fn (User $record): bool => $record->two_factor_confirmed_at !== null),
                IconColumn::make('is_active')->label(__('core::users.active'))->boolean(),
                TextColumn::make('locked_at')->label(__('core::users.locked'))->since()->placeholder('—')->color('danger'),
                TextColumn::make('last_login_at')->label(__('core::users.last_login'))->since()->placeholder(__('core::users.never')),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, User $record): array {
                        $data['role_ids'] = $record->roles->map(fn (Model $role): string => self::key($role))->all();

                        return $data;
                    })
                    ->using(fn (User $record, array $data): User => self::save($record, $data)),
                Action::make('reset_mfa')->label(__('core::users.reset_mfa'))->icon(Heroicon::OutlinedKey)->color('danger')
                    ->requiresConfirmation()->modalDescription(__('core::users.reset_mfa_help'))
                    ->visible(fn (User $record): bool => $record->two_factor_confirmed_at !== null)
                    ->authorize(fn (User $record): bool => Auth::user()?->can('manageAccess', $record) ?? false)
                    ->action(function (User $record): void {
                        $actor = Auth::user();
                        app(TwoFactorAuthentication::class)->disable($record, $actor instanceof User ? $actor : null);
                        Notification::make()->title(__('core::users.mfa_reset_done'))->success()->send();
                    }),
                Action::make('unlock')->label(__('core::users.unlock'))->icon(Heroicon::OutlinedLockOpen)
                    ->requiresConfirmation()->modalDescription(__('core::users.unlock_help'))
                    ->visible(fn (User $record): bool => $record->locked_at !== null)
                    ->authorize(fn (User $record): bool => Auth::user()?->can('manageAccess', $record) ?? false)
                    ->action(function (User $record): void {
                        $actor = Auth::user();

                        if ($actor instanceof User) {
                            app(AccountLockout::class)->unlock($record, $actor);
                            Notification::make()->title(__('core::users.unlocked'))->success()->send();
                        }
                    }),
                Action::make('resend_invitation')->label(__('core::users.resend_invitation'))->icon(Heroicon::OutlinedEnvelope)
                    ->visible(fn (User $record): bool => ! $record->isDirectoryAccount() && $record->last_login_at === null)
                    ->authorize(fn (User $record): bool => Auth::user()?->can('manageAccess', $record) ?? false)
                    ->action(function (User $record): void {
                        $actor = Auth::user();

                        if ($actor instanceof User) {
                            app(InviteUser::class)->sendInvitation($record, $actor);
                            Notification::make()->title(__('core::users.invitation_sent'))->success()->send();
                        }
                    }),
                Action::make('remove')->label(__('core::users.remove'))->icon(Heroicon::OutlinedUserMinus)->color('danger')
                    ->requiresConfirmation()->modalDescription(__('core::users.remove_help'))
                    ->authorize(fn (User $record): bool => Auth::user()?->can('manageAccess', $record) ?? false)
                    ->action(fn (User $record) => app(RemoveFromOrganization::class)->handle($record)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }

    /**
     * @param  array<mixed>  $data
     */
    public static function save(User $record, array $data): User
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User && $actor->can('update', $record), 403);

        if (! $record->isDirectoryAccount() && is_string($data['name'] ?? null)) {
            $record->forceFill(['name' => trim($data['name'])]);
        }

        if ($actor->can('deactivate', $record) && is_bool($data['is_active'] ?? null)) {
            $record->forceFill(['is_active' => $data['is_active']]);
        }

        $record->save();

        if (! $record->isDirectoryAccount() && $actor->can('manageAccess', $record)) {
            $record->syncRoles(Role::query()->whereKey(self::roleIds($data))->whereIn('id', array_keys(self::roleOptions()))->get());
        }

        return $record;
    }

    /**
     * @param  array<mixed>  $data
     * @return list<string>
     */
    public static function roleIds(array $data): array
    {
        $ids = $data['role_ids'] ?? [];

        return is_array($ids) ? array_values(array_map(strval(...), array_filter($ids, fn (mixed $id): bool => is_string($id) || is_int($id)))) : [];
    }

    private static function key(Model $role): string
    {
        $key = $role->getKey();

        return is_int($key) || is_string($key) ? (string) $key : '';
    }

    /**
     * Default roles and the roles of the current organization.
     *
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        $organizationId = app(OrganizationContext::class)->id();

        return Role::query()
            ->where(fn (Builder $query) => $query->whereNull('organization_id')->orWhere('organization_id', $organizationId))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Role $role): array => [self::key($role) => Str::headline($role->name)])
            ->all();
    }
}
