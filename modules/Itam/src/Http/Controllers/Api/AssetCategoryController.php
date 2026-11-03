<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Controllers\Api;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\Category;
use Modules\Itam\Http\Resources\AssetCategoryResource;

final readonly class AssetCategoryController
{
    public function __construct(private Gate $gate) {}

    /**
     * List the asset categories and their attributes.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', Asset::class);

        return AssetCategoryResource::collection(Category::query()->orderBy('name')->get());
    }
}
