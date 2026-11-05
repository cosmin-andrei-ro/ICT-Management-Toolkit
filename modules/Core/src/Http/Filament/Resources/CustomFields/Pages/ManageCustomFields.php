<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Resources\CustomFields\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Core\Http\Filament\Resources\CustomFields\CustomFieldResource;

final class ManageCustomFields extends ManageRecords
{
    protected static string $resource = CustomFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
