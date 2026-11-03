<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Http\Controllers\Api;

use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Bia\Http\Requests\Api\SaveProcessRequest;
use Modules\Bia\Http\Resources\ProcessResource;
use Modules\Core\Http\Api\ApiQuery;

/**
 * Business processes and their recovery objectives through the API (ADR 0064).
 * Impact analyses are filled in, submitted and approved on screen.
 */
final readonly class ProcessController
{
    private const array RELATIONS = ['department', 'owner', 'deputy'];

    public function __construct(private Gate $gate) {}

    /**
     * List business processes.
     *
     * Filters: `filter[status]`, `filter[tier]` (1–4), `filter[department_id]`, `filter[owner_id]`,
     * `filter[approved]` (true/false), `filter[search]` (name). Sorts: `name`, `tier`, `rto_minutes`,
     * `next_review_on`, `updated_at`; by name by default.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', BusinessProcess::class);
        $uuid = fn (string $column): Closure => fn (Builder $query, string $value) => Str::isUuid($value) ? $query->where($column, $value) : $query->whereRaw('false');

        $processes = ApiQuery::paginate($request, BusinessProcess::query()->with(self::RELATIONS), [
            'status' => fn (Builder $query, string $value) => $query->where('status', $value),
            'tier' => fn (Builder $query, string $value) => $query->where('tier', ctype_digit($value) ? (int) $value : 0),
            'department_id' => $uuid('department_id'),
            'owner_id' => $uuid('owner_id'),
            'approved' => fn (Builder $query, string $value) => $value === 'true' ? $query->whereNotNull('approved_at') : $query->whereNull('approved_at'),
            'search' => fn (Builder $query, string $value) => $query->where('name', 'ilike', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value).'%'),
        ], ['name' => 'name', 'tier' => 'tier', 'rto_minutes' => 'rto_minutes', 'next_review_on' => 'next_review_on', 'updated_at' => 'updated_at'], 'name');

        return ProcessResource::collection($processes);
    }

    /**
     * Show a business process.
     */
    public function show(BusinessProcess $process): ProcessResource
    {
        $this->gate->authorize('view', $process);

        return new ProcessResource($process->load(self::RELATIONS));
    }

    /**
     * Register a business process.
     */
    public function store(SaveProcessRequest $request): JsonResponse
    {
        $this->gate->authorize('create', BusinessProcess::class);
        $process = $this->save($request, new BusinessProcess);

        return (new ProcessResource($process->load(self::RELATIONS)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Change a business process: only the fields sent.
     */
    public function update(SaveProcessRequest $request, BusinessProcess $process): ProcessResource
    {
        $this->gate->authorize('update', $process);

        return new ProcessResource($this->save($request, $process)->load(self::RELATIONS));
    }

    private function save(SaveProcessRequest $request, BusinessProcess $process): BusinessProcess
    {
        $input = $request->validated();
        $text = fn (string $key): ?string => is_string($input[$key] ?? null) && trim($input[$key]) !== '' ? trim($input[$key]) : null;
        $fields = ['status' => $process->exists ? $process->status : 'active'];

        foreach (['name', 'department_id', 'owner_id', 'deputy_id', 'description', 'peak_periods', 'status'] as $key) {
            if (array_key_exists($key, $input)) {
                $fields[$key] = $text($key);
            }
        }

        // Same fields as the screen's form, which saves the model directly.
        $process->fill($fields)->save();

        return $process;
    }
}
