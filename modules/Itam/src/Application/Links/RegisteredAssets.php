<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Links;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Links\PhysicalAsset;
use Modules\Core\Domain\Links\PhysicalAssets;
use Modules\Itam\Domain\Enums\AssetStatus;
use Modules\Itam\Domain\Models\Asset;

/**
 * The asset register for other modules (ADR 0047): DCIM mounts these assets
 * in racks.
 */
final readonly class RegisteredAssets implements PhysicalAssets
{
    public function find(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $found = [];

        foreach (Asset::query()->with('category')->whereKey($ids)->get() as $asset) {
            $found[$asset->id] = self::summary($asset);
        }

        return $found;
    }

    public function search(string $term, int $limit = 20): array
    {
        $like = LinkedRecords::like(trim($term));

        return array_values(Asset::query()->with('category')
            ->whereNotIn('status', [AssetStatus::Retired, AssetStatus::Lost, AssetStatus::Disposed])
            ->when(trim($term) !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->whereLike('asset_tag', $like)->orWhereLike('name', $like)->orWhereLike('serial_number', $like)->orWhereLike('model', $like)))
            ->orderBy('asset_tag')
            ->limit(max(1, min($limit, 50)))
            ->get()
            ->map(self::summary(...))
            ->all());
    }

    private static function summary(Asset $asset): PhysicalAsset
    {
        return new PhysicalAsset(
            $asset->id,
            $asset->asset_tag,
            $asset->displayName(),
            $asset->manufacturer,
            $asset->model,
            $asset->serial_number,
            $asset->status->label(),
            $asset->location_id,
            route('itam.assets.show', $asset),
        );
    }
}
