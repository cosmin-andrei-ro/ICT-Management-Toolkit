<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Resources\ApiTokens;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Core\Application\Api\IssueApiToken;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Filament\Resources\ApiTokens\Pages\ManageApiTokens;

/**
 * Every API token that works in the organization (ADR 0061), for the
 * administrators who must be able to stop one at once.
 */
final class ApiTokenResource extends Resource
{
    protected static ?string $model = PersonalAccessToken::class;

    protected static ?string $slug = 'api-tokens';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    public static function getNavigationGroup(): string
    {
        return __('core::directory.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('core::api_tokens.admin.title');
    }

    public static function getModelLabel(): string
    {
        return __('core::api_tokens.admin.title');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('core.api.manage');
    }

    /**
     * Tokens bound to this organization, and older unbound tokens of members who
     * belong to it alone: an unbound token of a shared account may be used in
     * another organization, which this one must not list or revoke.
     *
     * @return Builder<PersonalAccessToken>
     */
    public static function getEloquentQuery(): Builder
    {
        $organizationId = app(OrganizationContext::class)->requireId();

        return PersonalAccessToken::query()->with('tokenable')->where('tokenable_type', 'core.user')->where(fn (Builder $query) => $query
            ->where('organization_id', $organizationId)
            ->orWhere(fn (Builder $query) => $query->whereNull('organization_id')->whereIn('tokenable_id', fn (QueryBuilder $members) => $members->select('user_id')->from('core_organization_user')
                ->groupBy('user_id')
                ->havingRaw('count(*) = 1')
                ->havingRaw('bool_and(organization_id = ?)', [$organizationId]))));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tokenable.name')->label(__('core::api_tokens.admin.owner'))->searchable(),
                TextColumn::make('name')->label(__('core::api_tokens.admin.name'))->searchable(),
                TextColumn::make('abilities')->label(__('core::api_tokens.admin.abilities'))->badge(),
                TextColumn::make('last_used_at')->label(__('core::api_tokens.admin.last_used'))->since()->placeholder(__('core::api_tokens.admin.never')),
                TextColumn::make('expires_at')->label(__('core::api_tokens.admin.expires'))->date()->placeholder(__('core::api_tokens.admin.never')),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('revoke')->label(__('core::api_tokens.admin.revoke'))->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                    ->requiresConfirmation()->modalDescription(__('core::api_tokens.admin.revoke_help'))
                    ->authorize(fn (): bool => self::canAccess())
                    ->action(function (PersonalAccessToken $record): void {
                        $actor = Auth::user();
                        $owner = $record->tokenable;

                        $key = $record->getKey();

                        if ($actor instanceof User && $owner instanceof User && (is_int($key) || is_string($key))) {
                            app(IssueApiToken::class)->revoke($owner, (string) $key, $actor);
                            Notification::make()->title(__('core::api_tokens.admin.revoked'))->success()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageApiTokens::route('/')];
    }
}
