<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Filament\Resources\SoftwareProducts\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Itam\Http\Filament\Resources\SoftwareProducts\SoftwareProductResource;

final class ManageSoftwareProducts extends ManageRecords
{
    protected static string $resource = SoftwareProductResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
