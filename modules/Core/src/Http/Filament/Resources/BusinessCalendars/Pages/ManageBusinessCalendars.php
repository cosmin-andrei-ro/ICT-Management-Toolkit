<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Resources\BusinessCalendars\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Core\Http\Filament\Resources\BusinessCalendars\BusinessCalendarResource;

final class ManageBusinessCalendars extends ManageRecords
{
    protected static string $resource = BusinessCalendarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(fn (array $data): array => BusinessCalendarResource::fromForm($data)),
        ];
    }
}
