<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Filament\Resources\InboundEmails\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Ticketing\Http\Filament\Resources\InboundEmails\InboundEmailResource;

final class ListInboundEmails extends ListRecords
{
    protected static string $resource = InboundEmailResource::class;
}
