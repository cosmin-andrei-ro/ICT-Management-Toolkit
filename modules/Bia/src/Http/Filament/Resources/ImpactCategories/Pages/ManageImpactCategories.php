<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Http\Filament\Resources\ImpactCategories\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Bia\Application\Actions\ProvisionDefaultMatrix;
use Modules\Bia\Http\Filament\Resources\ImpactCategories\ImpactCategoryResource;

final class ManageImpactCategories extends ManageRecords
{
    protected static string $resource = ImpactCategoryResource::class;

    public function mount(): void
    {
        parent::mount();
        app(ProvisionDefaultMatrix::class)->handle();
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->mutateDataUsing(fn (array $data): array => ImpactCategoryResource::listLevels($data))];
    }
}
