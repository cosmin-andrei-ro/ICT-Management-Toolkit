<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Application\Links;

use Illuminate\Support\Facades\Gate;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\User;
use Modules\Dcim\Domain\Models\Rack;

/**
 * Racks for the global search, only for those who may view them.
 */
final class DcimSearchRecords
{
    /**
     * @return list<LinkedRecord>
     */
    public static function racks(string $term, User $viewer): array
    {
        $gate = Gate::forUser($viewer);

        return array_values(Rack::query()->whereLike('name', LinkedRecords::like($term))->orderBy('name')->limit(20)->get()
            ->filter(fn (Rack $rack): bool => $gate->allows('view', $rack))
            ->map(fn (Rack $rack): LinkedRecord => new LinkedRecord($rack->id, $rack->name, route('dcim.racks.show', $rack)))
            ->all());
    }
}
