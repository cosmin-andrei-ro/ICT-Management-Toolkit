<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Application\Links;

use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\RackPlacements;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\User;
use Modules\Dcim\Domain\Models\Mount;

/**
 * Where an asset is mounted, for its page in the asset register.
 */
final readonly class MountedAssets implements RackPlacements
{
    public function of(string $assetId, User $viewer): ?LinkedRecord
    {
        if (! $viewer->can('dcim.infrastructure.view')) {
            return null;
        }

        $mount = Mount::query()->with('rack.location')->where('asset_id', $assetId)->first();

        if ($mount === null) {
            return null;
        }

        $room = Location::paths(false)[$mount->rack->location_id] ?? $mount->rack->location->name;

        return new LinkedRecord(
            $mount->id,
            __('dcim::racks.placement', ['rack' => $mount->rack->name, 'units' => $mount->unitsLabel(), 'face' => $mount->face->label(), 'room' => $room]),
            route('dcim.racks.show', $mount->rack),
        );
    }
}
