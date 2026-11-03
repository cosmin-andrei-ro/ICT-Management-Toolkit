<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Resources\OrganizationInvitations;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\OrganizationInvitation;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Filament\Resources\OrganizationInvitations\Pages\ListOrganizationInvitations;
use Override;

/**
 * This organization's invitations to people who already have an account, until
 * they answer. Shows only the address the administrator typed: the rest of the
 * account belongs to the person until they join.
 */
final class OrganizationInvitationResource extends Resource
{
    protected static ?string $model = OrganizationInvitation::class;

    protected static ?string $slug = 'invitations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    public static function getModelLabel(): string
    {
        return __('core::invitations.admin.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('core::invitations.admin.plural_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('core::directory.navigation_group');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('core.user.create');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * The model has no organization scope, because its person reads it from any organization.
     *
     * @return Builder<OrganizationInvitation>
     */
    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return OrganizationInvitation::query()->with(['user', 'inviter'])->where('organization_id', app(OrganizationContext::class)->requireId());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.email')->label(__('core::invitations.admin.email')),
                TextColumn::make('inviter.name')->label(__('core::invitations.admin.invited_by'))->placeholder('—'),
                TextColumn::make('created_at')->label(__('core::invitations.admin.sent'))->date(),
                TextColumn::make('expires_at')->label(__('core::invitations.admin.expires'))->date()
                    ->color(fn (OrganizationInvitation $record): ?string => $record->expires_at->isPast() ? 'danger' : null),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('core::invitations.admin.empty'))
            ->recordActions([
                Action::make('cancel')->label(__('core::invitations.admin.cancel'))->icon(Heroicon::OutlinedXMark)->color('danger')
                    ->requiresConfirmation()->modalDescription(__('core::invitations.admin.cancel_help'))
                    ->authorize(fn (): bool => self::canAccess())
                    ->action(function (OrganizationInvitation $record, AuditTrail $trail): void {
                        $trail->record(AuditEvent::Invited, $record->user, null, ['email' => $record->user->email, 'cancelled' => true]);
                        $record->delete();
                        Notification::make()->title(__('core::invitations.admin.cancelled'))->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListOrganizationInvitations::route('/')];
    }
}
