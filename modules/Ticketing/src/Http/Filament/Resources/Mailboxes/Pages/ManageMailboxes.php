<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Filament\Resources\Mailboxes\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Ticketing\Http\Filament\Resources\Mailboxes\MailboxResource;

final class ManageMailboxes extends ManageRecords
{
    protected static string $resource = MailboxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
