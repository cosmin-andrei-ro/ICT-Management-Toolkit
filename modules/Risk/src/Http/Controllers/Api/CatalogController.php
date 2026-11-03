<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Http\Controllers\Api;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Modules\Risk\Domain\Models\CatalogEntry;
use Modules\Risk\Domain\Models\Risk;

final readonly class CatalogController
{
    public function __construct(private Gate $gate) {}

    /**
     * List the threats and vulnerabilities a risk can name in its assessment.
     */
    public function index(): JsonResponse
    {
        $this->gate->authorize('viewAny', Risk::class);

        return response()->json(['data' => CatalogEntry::query()->orderBy('kind')->orderBy('name')->get()->map(fn (CatalogEntry $entry): array => [
            'id' => $entry->id, 'kind' => $entry->kind->value, 'name' => $entry->name, 'description' => $entry->description, 'is_active' => $entry->is_active,
        ])->all()]);
    }
}
