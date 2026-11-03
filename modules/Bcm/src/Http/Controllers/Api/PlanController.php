<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Http\Controllers\Api;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Bcm\Application\Policies\PlanPolicy;
use Modules\Bcm\Domain\Enums\PlanType;
use Modules\Bcm\Domain\Models\Activation;
use Modules\Bcm\Domain\Models\Exercise;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bcm\Domain\Models\PlanVersion;
use Modules\Bcm\Http\Requests\Api\SavePlanRequest;
use Modules\Bcm\Http\Resources\ActivationResource;
use Modules\Bcm\Http\Resources\ExerciseResource;
use Modules\Bcm\Http\Resources\PlanResource;
use Modules\Core\Domain\Models\User;
use Modules\Core\Http\Api\ApiQuery;

/**
 * Continuity and recovery plans through the API (ADR 0064): the plan in force
 * for whoever has to run it, exercises and activations to read. Versions are
 * written, submitted and approved on screen.
 */
final readonly class PlanController
{
    private const array RELATIONS = ['owner', 'deputy'];

    public function __construct(private Gate $gate) {}

    /**
     * List plans.
     *
     * Filters: `filter[type]` (bcp, drp), `filter[status]`, `filter[owner_id]`, `filter[approved]` (true/false),
     * `filter[search]` (name). Sorts: `name`, `next_review_on`, `last_exercised_on`, `updated_at`; by name by default.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', Plan::class);

        $plans = ApiQuery::paginate($request, Plan::query()->with(self::RELATIONS), [
            'type' => fn (Builder $query, string $value) => $query->where('type', $value),
            'status' => fn (Builder $query, string $value) => $query->where('status', $value),
            'owner_id' => fn (Builder $query, string $value) => Str::isUuid($value) ? $query->where('owner_id', $value) : $query->whereRaw('false'),
            'approved' => fn (Builder $query, string $value) => $value === 'true' ? $query->whereNotNull('approved_version') : $query->whereNull('approved_version'),
            'search' => fn (Builder $query, string $value) => $query->where('name', 'ilike', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value).'%'),
        ], ['name' => 'name', 'next_review_on' => 'next_review_on', 'last_exercised_on' => 'last_exercised_on', 'updated_at' => 'updated_at'], 'name');

        return PlanResource::collection($plans);
    }

    /**
     * Show a plan with the version in force: objective, activation, team and recovery steps.
     */
    public function show(Plan $plan): PlanResource
    {
        $this->gate->authorize('view', $plan);
        $inForce = $plan->approved_version !== null
            ? PlanVersion::query()->with(['team.user', 'steps.responsibleUser'])->where('plan_id', $plan->id)->where('version', $plan->approved_version)->first()
            : null;

        return (new PlanResource($plan->load(self::RELATIONS)))->withVersionInForce($inForce);
    }

    /**
     * Create a plan.
     */
    public function store(SavePlanRequest $request, PlanPolicy $policy): JsonResponse
    {
        $this->gate->authorize('create', Plan::class);

        return (new PlanResource($this->save($request, $policy, new Plan)->load(self::RELATIONS)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Change a plan's name, type, description, owner, deputy or status: only the fields sent.
     */
    public function update(SavePlanRequest $request, Plan $plan, PlanPolicy $policy): PlanResource
    {
        $this->gate->authorize('update', $plan);

        return new PlanResource($this->save($request, $policy, $plan)->load(self::RELATIONS));
    }

    /**
     * List exercises. Filters: `filter[plan_id]`, `filter[status]`. Sort: `scheduled_on` (newest first by default).
     */
    public function exercises(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', Plan::class);

        return ExerciseResource::collection(ApiQuery::paginate($request, Exercise::query(), [
            'plan_id' => fn (Builder $query, string $value) => Str::isUuid($value) ? $query->where('plan_id', $value) : $query->whereRaw('false'),
            'status' => fn (Builder $query, string $value) => $query->where('status', $value),
        ], ['scheduled_on' => 'scheduled_on'], '-scheduled_on'));
    }

    /**
     * List activations: real incidents the plans were used for. Filter: `filter[plan_id]`. Sort: `activated_at` (newest first by default).
     */
    public function activations(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', Plan::class);

        return ActivationResource::collection(ApiQuery::paginate($request, Activation::query(), [
            'plan_id' => fn (Builder $query, string $value) => Str::isUuid($value) ? $query->where('plan_id', $value) : $query->whereRaw('false'),
        ], ['activated_at' => 'activated_at'], '-activated_at'));
    }

    private function save(SavePlanRequest $request, PlanPolicy $policy, Plan $plan): Plan
    {
        $input = $request->validated();
        $text = fn (string $key): ?string => is_string($input[$key] ?? null) && trim($input[$key]) !== '' ? trim($input[$key]) : null;
        $type = PlanType::tryFrom($text('type') ?? '') ?? $plan->type ?? PlanType::Bcp;
        $actor = $request->user();

        // As on screen: a team lead may only create or keep recovery plans (DRP).
        if (! $actor instanceof User || ! $policy->manages($actor, $type)) {
            throw ValidationException::withMessages(['type' => __('bcm::plans.errors.type')]);
        }

        $fields = ['status' => $plan->exists ? $plan->status : 'active'];

        foreach (['type', 'name', 'description', 'owner_id', 'deputy_id', 'status'] as $key) {
            if (array_key_exists($key, $input)) {
                $fields[$key] = $text($key);
            }
        }

        $plan->fill($fields)->save();

        return $plan;
    }
}
