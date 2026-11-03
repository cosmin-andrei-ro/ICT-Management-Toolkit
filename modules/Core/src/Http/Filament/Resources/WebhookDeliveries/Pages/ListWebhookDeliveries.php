<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Resources\WebhookDeliveries\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Http\Filament\Resources\WebhookDeliveries\WebhookDeliveryResource;

final class ListWebhookDeliveries extends ListRecords
{
    protected static string $resource = WebhookDeliveryResource::class;
}
