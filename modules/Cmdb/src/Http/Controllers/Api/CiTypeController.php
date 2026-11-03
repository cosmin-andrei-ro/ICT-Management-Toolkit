<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Http\Controllers\Api;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Cmdb\Http\Resources\CiTypeResource;

final readonly class CiTypeController
{
    public function __construct(private Gate $gate) {}

    /**
     * List the configuration item types and their attributes.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', ConfigurationItem::class);

        return CiTypeResource::collection(CiType::query()->orderBy('name')->get());
    }
}
