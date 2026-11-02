<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Http\Filament\Resources\TimeHorizons\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Bia\Application\Actions\ProvisionDefaultMatrix;
use Modules\Bia\Http\Filament\Resources\TimeHorizons\TimeHorizonResource;

final class ManageTimeHorizons extends ManageRecords
{
    protected static string $resource = TimeHorizonResource::class;

    public function mount(): void
    {
        parent::mount();
        app(ProvisionDefaultMatrix::class)->handle();
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
