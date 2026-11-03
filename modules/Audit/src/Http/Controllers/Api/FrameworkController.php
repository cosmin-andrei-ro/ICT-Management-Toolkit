<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Controllers\Api;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Audit\Domain\Models\Audit;
use Modules\Audit\Domain\Models\Framework;
use Modules\Audit\Domain\Models\Requirement;
use Modules\Audit\Http\Resources\AuditResource;
use Modules\Audit\Http\Resources\FrameworkResource;
use Modules\Audit\Http\Resources\RequirementResource;
use Modules\Core\Http\Api\ApiQuery;

/**
 * Frameworks, their requirements and audits, read-only (ADR 0063).
 */
final readonly class FrameworkController
{
    public function __construct(private Gate $gate) {}

    /**
     * List the frameworks the organization follows.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', Framework::class);

        return FrameworkResource::collection(Framework::query()->withCount('requirements')->orderBy('name')->get());
    }

    /**
     * List a framework's requirements, with applicability, state and the controls meeting them.
     *
     * Filters: `filter[state]`, `filter[group]`, `filter[search]` (reference or title). Sort: `position` (default), `reference`.
     */
    public function requirements(Request $request, Framework $framework): AnonymousResourceCollection
    {
        $this->gate->authorize('view', $framework);

        $requirements = ApiQuery::paginate($request, Requirement::query()->where('framework_id', $framework->id)->with('controls', 'framework'), [
            'group' => fn (Builder $query, string $value) => $query->where('group', $value),
            'search' => fn (Builder $query, string $value) => $query->where(fn (Builder $query) => $query
                ->where('reference', 'ilike', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value).'%')
                ->orWhere('title', 'ilike', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value).'%')),
        ], ['position' => 'position', 'reference' => 'reference'], 'position');

        return RequirementResource::collection($requirements);
    }

    /**
     * List audits. Filters: `filter[status]`, `filter[kind]`. Sorts: `starts_on` (newest first by default), `reference`.
     */
    public function audits(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', Audit::class);

        return AuditResource::collection(ApiQuery::paginate($request, Audit::query(), [
            'status' => fn (Builder $query, string $value) => $query->where('status', $value),
            'kind' => fn (Builder $query, string $value) => $query->where('kind', $value),
        ], ['starts_on' => 'starts_on', 'reference' => 'number'], '-starts_on'));
    }
}
