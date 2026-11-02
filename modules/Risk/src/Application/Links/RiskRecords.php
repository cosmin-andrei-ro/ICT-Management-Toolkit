<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application\Links;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\User;
use Modules\Risk\Domain\Models\Risk;

/**
 * Risks of the register, as other modules link to them (LinkedRecords): only those the viewer may see.
 */
final class RiskRecords
{
    /**
     * @param  list<string>  $ids
     * @return list<LinkedRecord>
     */
    public static function resolve(array $ids, User $viewer): array
    {
        return self::records(Risk::query()->whereKey($ids)->limit(100)->get(), $viewer);
    }

    /**
     * @return list<LinkedRecord>
     */
    public static function search(string $term, User $viewer): array
    {
        $like = LinkedRecords::like($term);
        // "R-0012" finds the risk by its reference.
        $reference = preg_match('/^R-?0*(\d{1,9})$/i', $term, $match) === 1 ? (int) $match[1] : null;

        return self::records(Risk::query()
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->whereLike('title', $like)->when($reference !== null, fn (Builder $byCode) => $byCode->orWhere('reference', $reference))))
            ->latest()
            ->limit(50)
            ->get(), $viewer);
    }

    /**
     * @param  Collection<int, Risk>  $risks
     * @return list<LinkedRecord>
     */
    private static function records(Collection $risks, User $viewer): array
    {
        $gate = Gate::forUser($viewer);

        return array_values($risks
            ->filter(fn (Risk $risk): bool => $gate->allows('view', $risk))
            ->map(fn (Risk $risk): LinkedRecord => new LinkedRecord($risk->id, $risk->code().' '.$risk->title, route('risk.risks.show', $risk), $risk->status->label()))
            ->all());
    }
}
