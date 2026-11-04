<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Resources\Tags\Pages;

use Filament\Resources\Pages\ManageRecords;
use Modules\Core\Http\Filament\Resources\Tags\TagResource;

final class ManageTagList extends ManageRecords
{
    protected static string $resource = TagResource::class;
}
