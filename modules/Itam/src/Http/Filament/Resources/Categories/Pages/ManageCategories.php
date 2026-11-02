<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Filament\Resources\Categories\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Itam\Application\Actions\ProvisionDefaultCategories;
use Modules\Itam\Http\Filament\Resources\Categories\CategoryResource;

final class ManageCategories extends ManageRecords
{
    protected static string $resource = CategoryResource::class;

    public function mount(): void
    {
        parent::mount();
        app(ProvisionDefaultCategories::class)->handle();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
