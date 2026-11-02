<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Http\Filament\Resources\OptionValues\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Applications\Application\Options\DefaultOptions;
use Modules\Applications\Application\Options\OptionCatalog;
use Modules\Applications\Http\Filament\Resources\OptionValues\OptionValueResource;

final class ManageOptionValues extends ManageRecords
{
    protected static string $resource = OptionValueResource::class;

    public function mount(): void
    {
        parent::mount();

        // Shows every list with its defaults before anyone has used it.
        $catalog = app(OptionCatalog::class);

        foreach (array_keys(DefaultOptions::lists()) as $list) {
            $catalog->values($list);
        }
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
