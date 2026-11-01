<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Http\Filament\Resources\CiTypes\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Cmdb\Application\Actions\ProvisionDefaultTypes;
use Modules\Cmdb\Http\Filament\Resources\CiTypes\CiTypeResource;

final class ManageCiTypes extends ManageRecords
{
    protected static string $resource = CiTypeResource::class;

    public function mount(): void
    {
        parent::mount();
        app(ProvisionDefaultTypes::class)->handle();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
