<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Application\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\PowerConnection;
use Modules\Dcim\Domain\Models\PowerSource;

/**
 * Plugs mounted equipment into a PDU or UPS, or unplugs it.
 */
final readonly class PlugIn
{
    public function handle(Mount $mount, PowerSource $source, ?string $outlet): PowerConnection
    {
        if (PowerConnection::query()->where('mount_id', $mount->id)->where('source_id', $source->id)->exists()) {
            throw ValidationException::withMessages(['powerSourceId' => __('dcim::power.errors.already_plugged')]);
        }

        if ($outlet !== null && PowerConnection::query()->where('source_id', $source->id)->whereRaw('lower(outlet) = ?', [mb_strtolower($outlet)])->exists()) {
            throw ValidationException::withMessages(['outlet' => __('dcim::power.errors.outlet_taken')]);
        }

        $connection = new PowerConnection;
        $connection->forceFill(['mount_id' => $mount->id, 'source_id' => $source->id, 'outlet' => $outlet])->save();

        return $connection;
    }

    public function unplug(PowerConnection $connection): void
    {
        $connection->delete();
    }
}
