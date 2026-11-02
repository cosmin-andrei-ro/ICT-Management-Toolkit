<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Http\Filament\Resources\CatalogEntries\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Risk\Application\Catalog\ProvisionCatalog;
use Modules\Risk\Http\Filament\Resources\CatalogEntries\CatalogEntryResource;

final class ManageCatalogEntries extends ManageRecords
{
    protected static string $resource = CatalogEntryResource::class;

    public function mount(): void
    {
        parent::mount();
        app(ProvisionCatalog::class)->handle();
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
