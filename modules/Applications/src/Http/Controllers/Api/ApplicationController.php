<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Http\Controllers\Api;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Modules\Applications\Application\Actions\SaveApplication;
use Modules\Applications\Domain\Enums\SheetStatus;
use Modules\Applications\Domain\Models\Application;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Applications\Http\Requests\Api\SaveApplicationRequest;
use Modules\Applications\Http\Resources\ApplicationResource;
use Modules\Core\Http\Api\ApiQuery;

/**
 * The application portfolio through the API (ADR 0064): applications, and the
 * documentation sheet in force, read-only. Sheets are written, submitted and
 * approved on screen.
 */
final readonly class ApplicationController
{
    private const array RELATIONS = ['department', 'businessOwner', 'technicalOwner', 'deputy'];

    public function __construct(private Gate $gate) {}

    /**
     * List applications.
     *
     * Filters: `filter[status]`, `filter[criticality]` (list keys), `filter[owner_id]` (business or technical owner,
     * or deputy), `filter[department_id]`, `filter[documented]` (true/false), `filter[search]` (code or name).
     * Sorts: `code`, `name`, `next_review_on`, `updated_at`; by code by default.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', Application::class);
        $like = fn (string $value): string => '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value).'%';

        $applications = ApiQuery::paginate($request, Application::query()->with(self::RELATIONS), [
            'status' => fn (Builder $query, string $value) => $query->where('status', $value),
            'criticality' => fn (Builder $query, string $value) => $query->where('criticality', $value),
            'owner_id' => fn (Builder $query, string $value) => Str::isUuid($value)
                ? $query->where(fn (Builder $query) => $query->where('business_owner_id', $value)->orWhere('technical_owner_id', $value)->orWhere('deputy_id', $value))
                : $query->whereRaw('false'),
            'department_id' => fn (Builder $query, string $value) => Str::isUuid($value) ? $query->where('department_id', $value) : $query->whereRaw('false'),
            'documented' => fn (Builder $query, string $value) => $value === 'true' ? $query->whereNotNull('current_version') : $query->whereNull('current_version'),
            'search' => fn (Builder $query, string $value) => $query->where(fn (Builder $query) => $query->where('code', 'ilike', $like($value))->orWhere('name', 'ilike', $like($value))),
        ], ['code' => 'code', 'name' => 'name', 'next_review_on' => 'next_review_on', 'updated_at' => 'updated_at'], 'code');

        return ApplicationResource::collection($applications);
    }

    /**
     * Show an application.
     */
    public function show(Application $application): ApplicationResource
    {
        $this->gate->authorize('view', $application);

        return new ApplicationResource($application->load(self::RELATIONS));
    }

    /**
     * The documentation sheet in force, chapter by chapter, as stored (keys follow the template's numbering).
     */
    public function sheet(Application $application): JsonResponse
    {
        $this->gate->authorize('view', $application);
        $sheet = Sheet::query()->where('application_id', $application->id)->where('status', SheetStatus::Approved->value)->latest('approved_at')->first();
        abort_if($sheet === null, Response::HTTP_NOT_FOUND, __('applications::api.no_sheet'));

        return response()->json(['data' => [
            'id' => $sheet->id,
            'version' => $sheet->major.'.'.$sheet->minor,
            'approved_at' => $sheet->approved_at?->toIso8601String(),
            'change_summary' => $sheet->change_summary,
            'content' => (object) $sheet->content,
        ]]);
    }

    /**
     * Register an application.
     */
    public function store(SaveApplicationRequest $request, SaveApplication $save): JsonResponse
    {
        $this->gate->authorize('create', Application::class);

        return (new ApplicationResource($this->save($request, $save, null)->load(self::RELATIONS)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Change an application's identity and owners: only the fields sent.
     */
    public function update(SaveApplicationRequest $request, Application $application, SaveApplication $save): ApplicationResource
    {
        $this->gate->authorize('update', $application);

        return new ApplicationResource($this->save($request, $save, $application)->load(self::RELATIONS));
    }

    private function save(SaveApplicationRequest $request, SaveApplication $save, ?Application $application): Application
    {
        $input = $request->validated();
        $value = fn (string $key, ?string $current): ?string => array_key_exists($key, $input)
            ? (is_string($input[$key]) && trim($input[$key]) !== '' ? trim($input[$key]) : null)
            : $current;

        return $save->handle($application, [
            'code' => $value('code', $application?->code) ?? '',
            'name' => $value('name', $application?->name) ?? '',
            'department_id' => $value('department_id', $application?->department_id),
            'department_email' => $value('department_email', $application?->department_email),
            'business_owner_id' => $value('business_owner_id', $application?->business_owner_id),
            'technical_owner_id' => $value('technical_owner_id', $application?->technical_owner_id),
            'deputy_id' => $value('deputy_id', $application?->deputy_id),
        ]);
    }
}
