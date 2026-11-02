<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Application\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Links\PhysicalAssets;
use Modules\Core\Domain\Models\Location;
use Modules\Dcim\Application\PowerGrid;
use Modules\Dcim\Domain\Enums\PowerKind;
use Modules\Dcim\Domain\Models\PowerSource;
use Modules\Dcim\Domain\Models\Rack;

/**
 * Creates or changes a feed, UPS or PDU, keeping the chain free of loops;
 * removes one nothing depends on.
 */
final readonly class SavePowerSource
{
    public function __construct(private PhysicalAssets $assets) {}

    /**
     * @param  array{location_id: string, kind: PowerKind, name: string, side: ?\Modules\Dcim\Domain\Enums\PowerSide, upstream_id: ?string, asset_id: ?string, rack_id: ?string, capacity_watts: ?int, notes: ?string}  $data
     */
    public function handle(?PowerSource $source, array $data): PowerSource
    {
        $errors = [];

        if (! Location::query()->whereKey($data['location_id'])->exists()) {
            $errors['locationId'] = __('dcim::racks.errors.location');
        }

        if (PowerSource::query()->whereRaw('lower(name) = ?', [mb_strtolower($data['name'])])->when($source !== null, fn ($query) => $query->whereKeyNot($source?->id))->exists()) {
            $errors['name'] = __('dcim::power.errors.name_taken');
        }

        if ($data['upstream_id'] !== null) {
            if ($data['kind'] === PowerKind::Feed) {
                $errors['upstreamId'] = __('dcim::power.errors.feed_upstream');
            } elseif (! PowerSource::query()->whereKey($data['upstream_id'])->exists()) {
                $errors['upstreamId'] = __('dcim::power.errors.upstream');
            } elseif ($source !== null && in_array($source->id, PowerGrid::read()->chain($data['upstream_id']), true)) {
                $errors['upstreamId'] = __('dcim::power.errors.loop');
            }
        }

        if ($data['asset_id'] !== null) {
            if ($this->assets->find([$data['asset_id']]) === []) {
                $errors['assetId'] = __('dcim::racks.errors.asset');
            } elseif (PowerSource::query()->where('asset_id', $data['asset_id'])->when($source !== null, fn ($query) => $query->whereKeyNot($source?->id))->exists()) {
                $errors['assetId'] = __('dcim::power.errors.asset_taken');
            }
        }

        if ($data['rack_id'] !== null && ! Rack::query()->whereKey($data['rack_id'])->exists()) {
            $errors['rackId'] = __('dcim::power.errors.rack');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $source ??= new PowerSource;
        $source->forceFill($data)->save();

        return $source;
    }

    public function delete(PowerSource $source): void
    {
        if ($source->downstream()->exists() || $source->connections()->exists()) {
            throw ValidationException::withMessages(['source' => __('dcim::power.errors.in_use')]);
        }

        $source->delete();
    }
}
