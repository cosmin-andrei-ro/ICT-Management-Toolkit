<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Application\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Models\Location;
use Modules\Dcim\Domain\Models\Rack;

/**
 * Creates or changes a rack. A rack is never made shorter than the
 * equipment it holds (the database refuses it too).
 */
final readonly class SaveRack
{
    /**
     * @param  array{location_id: string, name: string, row: ?string, position: ?int, height_u: int, max_power_watts: ?int, notes: ?string}  $data
     */
    public function handle(?Rack $rack, array $data): Rack
    {
        if (! Location::query()->whereKey($data['location_id'])->exists()) {
            throw ValidationException::withMessages(['locationId' => __('dcim::racks.errors.location')]);
        }

        if ($rack !== null) {
            $highest = $rack->mounts()->whereNotNull('position_u')->get()->max(fn ($mount) => $mount->topUnit()) ?? 0;

            if ($data['height_u'] < $highest) {
                throw ValidationException::withMessages(['heightU' => __('dcim::racks.errors.too_short', ['unit' => $highest])]);
            }
        }

        $rack ??= new Rack;
        $rack->forceFill($data)->save();

        return $rack;
    }
}
