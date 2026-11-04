<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Links;

use Illuminate\Support\Facades\Gate;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\User;

/**
 * Suppliers for the global search, only for those who may view them.
 */
final class SupplierRecords
{
    /**
     * @return list<LinkedRecord>
     */
    public static function search(string $term, User $viewer): array
    {
        $gate = Gate::forUser($viewer);

        if (! $gate->allows('viewAny', Supplier::class)) {
            return [];
        }

        return array_values(Supplier::query()->search($term)->orderBy('name')->limit(20)->get()
            ->filter(fn (Supplier $supplier): bool => $gate->allows('view', $supplier))
            ->map(fn (Supplier $supplier): LinkedRecord => new LinkedRecord($supplier->id, $supplier->name, route('core.suppliers.show', $supplier)))
            ->all());
    }
}
