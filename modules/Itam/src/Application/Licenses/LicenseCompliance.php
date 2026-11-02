<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Licenses;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Itam\Domain\Models\License;
use Modules\Itam\Domain\Models\SoftwareProduct;

/**
 * Compliance per product: seats bought on licences valid today against seats in
 * use on them. Expired licences count for nothing, so their seats show up as
 * excess until they are renewed or released.
 */
final class LicenseCompliance
{
    /**
     * @return Collection<int, ProductCompliance>
     */
    public function products(CarbonImmutable $today, string $search = '', bool $withExpired = false): Collection
    {
        $search = trim($search);
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search).'%';

        $products = SoftwareProduct::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->whereLike('name', $like)
                ->orWhereLike('publisher', $like)
                ->orWhereHas('licenses', fn ($licenses) => $licenses->whereLike('name', $like))))
            ->whereHas('licenses')
            ->with(['licenses' => fn ($licenses) => $licenses->withCount('assignments')->orderBy('name')])
            ->orderBy('name')
            ->limit(500)
            ->get();

        return $products
            ->map(fn (SoftwareProduct $product): ProductCompliance => self::summarize($product, $today, $withExpired))
            ->filter(fn (ProductCompliance $compliance): bool => $compliance->licenses->isNotEmpty() || $compliance->used > 0)
            ->values();
    }

    /**
     * Seats in use count on every licence, expired ones included; seats bought only on valid ones.
     */
    private static function summarize(SoftwareProduct $product, CarbonImmutable $today, bool $withExpired): ProductCompliance
    {
        $valid = $product->licenses->reject(fn (License $license): bool => $license->isExpiredOn($today));
        $site = $valid->contains(fn (License $license): bool => ! $license->model->isCounted());

        return new ProductCompliance(
            $product,
            $withExpired ? $product->licenses : $valid->values(),
            $site ? null : (int) $valid->sum(fn (License $license): int => $license->quantity ?? 0),
            (int) $product->licenses->sum(fn (License $license): int => $license->assignments_count ?? 0),
        );
    }
}
