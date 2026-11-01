<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Resources\Users\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Modules\Core\Application\Users\InviteUser;
use Modules\Core\Domain\Models\User;
use Modules\Core\Http\Filament\Resources\Users\UserResource;

final class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('invite')
                ->label(__('core::users.invite'))
                ->authorize(fn (): bool => Auth::user()?->can('create', User::class) ?? false)
                ->schema([
                    TextInput::make('name')->label(__('core::users.name'))->required()->maxLength(200),
                    TextInput::make('email')->label(__('core::users.email'))->email()->required()->maxLength(255)->helperText(__('core::users.invite_help')),
                    Select::make('role_ids')->label(__('core::users.roles'))->multiple()->required()
                        ->options(fn (): array => UserResource::roleOptions())
                        ->in(fn (): array => array_keys(UserResource::roleOptions())),
                ])
                ->action(function (array $data): void {
                    $actor = Auth::user();
                    abort_unless($actor instanceof User, 403);

                    try {
                        $invited = app(InviteUser::class)->handle(is_string($data['name'] ?? null) ? $data['name'] : '', is_string($data['email'] ?? null) ? $data['email'] : '', UserResource::roleIds($data), $actor);
                    } catch (InvalidArgumentException) {
                        Notification::make()->title(__('core::users.invite_failed'))->danger()->send();

                        return;
                    }

                    Notification::make()->title(__($invited ? 'core::users.invitation_sent' : 'core::users.added_existing'))->success()->send();
                }),
        ];
    }
}
