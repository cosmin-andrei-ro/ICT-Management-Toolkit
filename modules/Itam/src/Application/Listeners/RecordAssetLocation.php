<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Listeners;

use Modules\Core\Domain\Events\AssetPlaced;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Itam\Domain\Models\Asset;

/**
 * An asset mounted in a rack is in the rack's room (ADR 0047); the change is
 * in the asset's audit trail.
 */
final readonly class RecordAssetLocation
{
    public function __construct(private OrganizationContext $context) {}

    public function handle(AssetPlaced $event): void
    {
        $this->context->runAs($event->organizationId, function () use ($event): void {
            $asset = Asset::query()->find($event->assetId);

            if ($asset !== null && $asset->location_id !== $event->locationId) {
                $asset->forceFill(['location_id' => $event->locationId])->save();
            }
        });
    }
}
