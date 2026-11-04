<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Links;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\User;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\License;

/**
 * Assets and licences for the global search, only for those who may view them.
 * A licence is found by its name or product, never by its key.
 */
final class ItamSearchRecords
{
    /**
     * @return list<LinkedRecord>
     */
    public static function assets(string $term, User $viewer): array
    {
        $gate = Gate::forUser($viewer);

        if (! $gate->allows('viewAny', Asset::class)) {
            return [];
        }

        return array_values(Asset::query()->with('category')->search($term)->latest()->limit(20)->get()
            ->filter(fn (Asset $asset): bool => $gate->allows('view', $asset))
            ->map(fn (Asset $asset): LinkedRecord => new LinkedRecord($asset->id, $asset->asset_tag.' '.$asset->displayName(), route('itam.assets.show', $asset)))
            ->all());
    }

    /**
     * @return list<LinkedRecord>
     */
    public static function licenses(string $term, User $viewer): array
    {
        $gate = Gate::forUser($viewer);

        if (! $gate->allows('viewAny', License::class)) {
            return [];
        }

        $like = LinkedRecords::like($term);

        return array_values(License::query()->with('product')
            ->where(fn (Builder $match) => $match->whereLike('name', $like)->orWhereHas('product', fn (Builder $product) => $product->whereLike('name', $like)))
            ->latest()->limit(20)->get()
            ->filter(fn (License $license): bool => $gate->allows('view', $license))
            ->map(fn (License $license): LinkedRecord => new LinkedRecord($license->id, $license->product->name.' · '.$license->name, route('itam.licenses.show', $license)))
            ->all());
    }
}
