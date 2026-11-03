<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Controllers\Api;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Audit\Application\Actions\CloseFinding;
use Modules\Audit\Application\Actions\ManageFindingActions;
use Modules\Audit\Application\Actions\SaveFinding;
use Modules\Audit\Domain\Enums\ActionStatus;
use Modules\Audit\Domain\Enums\FindingSeverity;
use Modules\Audit\Domain\Models\Audit;
use Modules\Audit\Domain\Models\Finding;
use Modules\Audit\Domain\Models\FindingAction;
use Modules\Audit\Http\Requests\Api\FindingActionRequest;
use Modules\Audit\Http\Requests\Api\SaveFindingRequest;
use Modules\Audit\Http\Resources\FindingResource;
use Modules\Core\Domain\Models\User;
use Modules\Core\Http\Api\ApiQuery;

/**
 * Findings and their corrective actions through the API (ADR 0063).
 * Submitting a finding for verification and verifying it stay on screen.
 */
final readonly class FindingController
{
    private const array RELATIONS = ['owner', 'actions'];

    public function __construct(private Gate $gate) {}

    /**
     * List findings.
     *
     * Filters: `filter[status]`, `filter[severity]`, `filter[owner_id]`, `filter[audit_id]`,
     * `filter[control_id]`, `filter[search]` (title). Sorts: `reference`, `due_on`, `created_at`,
     * `updated_at`; newest first by default.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', Finding::class);
        $uuid = fn (string $column): Closure => fn (Builder $query, string $value) => Str::isUuid($value) ? $query->where($column, $value) : $query->whereRaw('false');

        $findings = ApiQuery::paginate($request, Finding::query()->with(self::RELATIONS), [
            'status' => fn (Builder $query, string $value) => $query->where('status', $value),
            'severity' => fn (Builder $query, string $value) => $query->where('severity', $value),
            'owner_id' => $uuid('owner_id'),
            'audit_id' => $uuid('audit_id'),
            'control_id' => $uuid('control_id'),
            'search' => fn (Builder $query, string $value) => $query->where('title', 'ilike', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value).'%'),
        ], ['reference' => 'number', 'due_on' => 'due_on', 'created_at' => 'created_at', 'updated_at' => 'updated_at'], '-created_at');

        return FindingResource::collection($findings);
    }

    /**
     * Show a finding and its corrective actions.
     */
    public function show(Finding $finding): FindingResource
    {
        $this->gate->authorize('view', $finding);

        return new FindingResource($finding->load(self::RELATIONS));
    }

    /**
     * Raise a finding, from an audit or on its own.
     */
    public function store(SaveFindingRequest $request, SaveFinding $save, CloseFinding $close): JsonResponse
    {
        $this->gate->authorize('create', Finding::class);
        $audit = null;

        if ($request->filled('audit_id')) {
            // Scoped lookup: an audit of another organization is not found.
            $audit = Audit::query()->find($request->string('audit_id')->toString()) ?? throw ValidationException::withMessages(['audit_id' => __('audit::api.unknown_audit')]);
        }

        $finding = $this->save($request, $save, $close, null, $audit);

        return (new FindingResource($finding->load(self::RELATIONS)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Change a finding while it is open: only the fields sent.
     */
    public function update(SaveFindingRequest $request, Finding $finding, SaveFinding $save, CloseFinding $close): FindingResource
    {
        $this->gate->authorize('update', $finding);

        return new FindingResource($this->save($request, $save, $close, $finding, null)->load(self::RELATIONS));
    }

    /**
     * Add a corrective action.
     */
    public function addAction(FindingActionRequest $request, Finding $finding, ManageFindingActions $actions): JsonResponse
    {
        $this->gate->authorize('work', $finding);

        try {
            $actions->add($finding, $request->string('title')->toString(), $request->filled('owner_id') ? $request->string('owner_id')->toString() : null, $request->filled('due_on') ? CarbonImmutable::createFromFormat('!Y-m-d', $request->string('due_on')->toString()) ?: null : null, $this->actor($request));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['owner_id' => $exception->getMessage()]);
        }

        return (new FindingResource($finding->refresh()->load(self::RELATIONS)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Move a corrective action on: planned, in progress, done or cancelled.
     */
    public function updateAction(FindingActionRequest $request, Finding $finding, FindingAction $action, ManageFindingActions $actions): FindingResource
    {
        abort_unless($action->finding_id === $finding->id, Response::HTTP_NOT_FOUND);
        $this->gate->authorize('progressAction', [$finding, $action]);
        $actions->changeStatus($action, $request->enum('status', ActionStatus::class) ?? ActionStatus::Planned, $this->actor($request));

        return new FindingResource($finding->refresh()->load(self::RELATIONS));
    }

    private function save(SaveFindingRequest $request, SaveFinding $save, CloseFinding $close, ?Finding $finding, ?Audit $audit): Finding
    {
        $input = $request->validated();
        $text = fn (mixed $value): ?string => is_string($value) ? $value : null;
        $value = fn (string $key, mixed $current): ?string => $text(array_key_exists($key, $input) ? $input[$key] : $current);
        $due = $value('due_on', $finding?->due_on?->toDateString());

        try {
            $finding = $save->handle($finding, [
                'title' => $value('title', $finding?->title) ?? '',
                'description' => $value('description', $finding?->description) ?? '',
                'severity' => FindingSeverity::from($value('severity', $finding?->severity->value) ?? FindingSeverity::Observation->value),
                'owner_id' => $value('owner_id', $finding?->owner_id),
                'due_on' => $due !== null ? (CarbonImmutable::createFromFormat('!Y-m-d', $due) ?: null) : null,
                'control_id' => $value('control_id', $finding?->control_id),
                'requirement_id' => $value('requirement_id', $finding?->requirement_id),
            ], $this->actor($request), $audit);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['owner_id' => __('audit::api.unknown_reference')]);
        }

        if (array_key_exists('root_cause', $input)) {
            $close->rootCause($finding, $text($input['root_cause']));
        }

        return $finding;
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $user;
    }
}
