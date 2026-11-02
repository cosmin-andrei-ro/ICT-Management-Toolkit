<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Application\Actions;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Events\AssetPlaced;
use Modules\Core\Domain\Links\PhysicalAssets;
use Modules\Core\Domain\Models\User;
use Modules\Dcim\Domain\Enums\MountFace;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\Rack;

/**
 * Mounts an asset in a rack, or moves it (ADR 0047). The units must exist
 * and be free on the sides the equipment takes; the asset register then
 * records the rack's room as the asset's location.
 */
final readonly class MountAsset
{
    public function __construct(private PhysicalAssets $assets) {}

    public function handle(Rack $rack, ?Mount $mount, string $assetId, ?int $position, int $height, MountFace $face, bool $fullDepth, ?int $powerWatts, ?string $notes, User $actor): Mount
    {
        if ($this->assets->find([$assetId]) === []) {
            throw ValidationException::withMessages(['assetId' => __('dcim::racks.errors.asset')]);
        }

        $exceptId = $mount?->id;
        $elsewhere = Mount::query()->with('rack')->where('asset_id', $assetId)->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))->first();

        if ($elsewhere !== null) {
            throw ValidationException::withMessages(['assetId' => __('dcim::racks.errors.already_mounted', ['rack' => $elsewhere->rack->name, 'units' => $elsewhere->unitsLabel()])]);
        }

        if ($position !== null && $position + $height - 1 > $rack->height_u) {
            throw ValidationException::withMessages(['positionU' => __('dcim::racks.errors.beyond_top', ['height' => $rack->height_u])]);
        }

        if ($position !== null) {
            $this->ensureFree($rack, $mount, $position, $height, $face, $fullDepth);
        }

        $mount ??= new Mount;
        $mount->forceFill([
            'rack_id' => $rack->id,
            'asset_id' => $assetId,
            'position_u' => $position,
            'height_u' => $height,
            'face' => $face,
            'full_depth' => $fullDepth,
            'power_watts' => $powerWatts,
            'notes' => $notes,
        ]);

        try {
            DB::transaction(function () use ($mount, $rack, $actor): void {
                $mount->save();
                event(new AssetPlaced($rack->organization_id, $mount->asset_id, $rack->location_id, $actor->id));
            });
        } catch (QueryException $exception) {
            // Someone took the units in between: the exclusion constraint refused it.
            if (in_array($exception->getCode(), ['23P01', '23505'], true)) {
                throw ValidationException::withMessages(['positionU' => __('dcim::racks.errors.taken_meanwhile')]);
            }

            throw $exception;
        }

        return $mount;
    }

    private function ensureFree(Rack $rack, ?Mount $mount, int $position, int $height, MountFace $face, bool $fullDepth): void
    {
        $wanted = range($position, $position + $height - 1);
        $exceptId = $mount?->id;
        $occupants = Mount::query()->where('rack_id', $rack->id)->whereNotNull('position_u')
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->get()
            ->filter(fn (Mount $other): bool => array_intersect($wanted, $other->units()) !== []
                && ($fullDepth || $other->full_depth || $other->face === $face));

        if ($occupants->isNotEmpty()) {
            $assets = $this->assets->find(array_values($occupants->map(fn (Mount $other): string => $other->asset_id)->all()));
            $names = $occupants->map(fn (Mount $other): string => ($assets[$other->asset_id] ?? null)?->label().' ('.$other->unitsLabel().')')->implode(', ');

            throw ValidationException::withMessages(['positionU' => __('dcim::racks.errors.occupied', ['equipment' => $names])]);
        }
    }
}
