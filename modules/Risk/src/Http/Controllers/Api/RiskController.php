<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Http\Controllers\Api;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Api\ApiQuery;
use Modules\Risk\Application\Actions\AssessRisk;
use Modules\Risk\Application\Actions\ManageRiskActions;
use Modules\Risk\Application\Actions\ManageRiskAssets;
use Modules\Risk\Application\Actions\SaveRisk;
use Modules\Risk\Application\Actions\SaveTreatment;
use Modules\Risk\Application\RiskAssets;
use Modules\Risk\Domain\Enums\ActionStatus;
use Modules\Risk\Domain\Enums\AssetType;
use Modules\Risk\Domain\Enums\RiskCategory;
use Modules\Risk\Domain\Enums\RiskStatus;
use Modules\Risk\Domain\Enums\Treatment;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\RiskAction;
use Modules\Risk\Http\Requests\Api\AssessRiskRequest;
use Modules\Risk\Http\Requests\Api\RiskActionRequest;
use Modules\Risk\Http\Requests\Api\RiskAssetRequest;
use Modules\Risk\Http\Requests\Api\SaveRiskRequest;
use Modules\Risk\Http\Requests\Api\TreatRiskRequest;
use Modules\Risk\Http\Resources\RiskResource;

/**
 * The risk register through the API (ADR 0063), with the screens' actions and
 * policy: risk managers register and change risks; a risk's owner (or a risk
 * manager) assesses and treats it. Acceptances stay on screen.
 */
final readonly class RiskController
{
    private const array RELATIONS = ['owner', 'catalogEntries', 'actions'];

    public function __construct(private Gate $gate, private OrganizationContext $context) {}

    /**
     * List risks.
     *
     * Filters: `filter[status]`, `filter[category]`, `filter[owner_id]`, `filter[treatment]`,
     * `filter[search]` (title). Sorts: `reference`, `title`, `next_review_on`, `created_at`,
     * `updated_at`; by reference by default.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', Risk::class);

        $risks = ApiQuery::paginate($request, Risk::query()->with(self::RELATIONS), [
            'status' => fn (Builder $query, string $value) => $query->where('status', $value),
            'category' => fn (Builder $query, string $value) => $query->where('category', $value),
            'treatment' => fn (Builder $query, string $value) => $query->where('treatment', $value),
            'owner_id' => fn (Builder $query, string $value) => Str::isUuid($value) ? $query->where('owner_id', $value) : $query->whereRaw('false'),
            'search' => fn (Builder $query, string $value) => $query->where('title', 'ilike', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value).'%'),
        ], ['reference' => 'reference', 'title' => 'title', 'next_review_on' => 'next_review_on', 'created_at' => 'created_at', 'updated_at' => 'updated_at'], 'reference');

        return RiskResource::collection($risks);
    }

    /**
     * Show a risk, with what it concerns, its threats and vulnerabilities and its actions.
     */
    public function show(Risk $risk, RiskAssets $assets): RiskResource
    {
        $this->gate->authorize('view', $risk);

        return (new RiskResource($risk->load(self::RELATIONS)))->withAssets($assets->resolve($risk->assets()));
    }

    /**
     * Register a risk.
     */
    public function store(SaveRiskRequest $request, SaveRisk $save): JsonResponse
    {
        $this->gate->authorize('create', Risk::class);

        return (new RiskResource($this->save($request, $save, null)->load(self::RELATIONS)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Change a risk's title, description, category, owner or status: only the fields sent.
     */
    public function update(SaveRiskRequest $request, Risk $risk, SaveRisk $save): RiskResource
    {
        $this->gate->authorize('update', $risk);

        return new RiskResource($this->save($request, $save, $risk)->load(self::RELATIONS));
    }

    /**
     * Assess the risk before treatment: likelihood and impact, threats and vulnerabilities, controls in place.
     */
    public function assess(AssessRiskRequest $request, Risk $risk, AssessRisk $assess): RiskResource
    {
        $this->gate->authorize('assess', $risk);
        $entries = array_values(array_filter((array) $request->input('catalog_entry_ids', $risk->catalogEntries()->pluck('risk_catalog_entries.id')->all()), is_string(...)));

        try {
            $assess->handle($risk, $this->score($request, 'likelihood'), $this->score($request, 'impact'), $entries, $request->has('existing_controls') ? $request->string('existing_controls')->toString() : $risk->existing_controls, $this->actor($request));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['catalog_entry_ids' => $exception->getMessage()]);
        }

        return new RiskResource($risk->refresh()->load(self::RELATIONS));
    }

    /**
     * Decide the treatment and the residual risk expected once it is in place.
     */
    public function treat(TreatRiskRequest $request, Risk $risk, SaveTreatment $treatment): RiskResource
    {
        $this->gate->authorize('assess', $risk);

        try {
            $treatment->handle($risk, $request->enum('treatment', Treatment::class), $request->has('treatment_plan') ? $request->string('treatment_plan')->toString() : $risk->treatment_plan, $this->score($request, 'residual_likelihood'), $this->score($request, 'residual_impact'), $this->actor($request));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['residual_likelihood' => $exception->getMessage()]);
        }

        return new RiskResource($risk->refresh()->load(self::RELATIONS));
    }

    /**
     * Link what the risk concerns: an application, a configuration item, a process or a supplier.
     */
    public function addAsset(RiskAssetRequest $request, Risk $risk, ManageRiskAssets $assets): Response
    {
        $this->gate->authorize('assess', $risk);

        try {
            $assets->add($risk, $request->enum('type', AssetType::class) ?? AssetType::Application, $request->string('id')->toString(), $this->actor($request));
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['id' => __('risk::api.unknown_asset')]);
        }

        return response()->noContent();
    }

    /**
     * Unlink what the risk concerns.
     */
    public function removeAsset(Request $request, Risk $risk, string $type, string $asset, ManageRiskAssets $assets): Response
    {
        $this->gate->authorize('assess', $risk);
        $assets->remove($risk, AssetType::tryFrom($type) ?? abort(Response::HTTP_NOT_FOUND), $asset, $this->actor($request));

        return response()->noContent();
    }

    /**
     * Add a treatment action.
     */
    public function addAction(RiskActionRequest $request, Risk $risk, ManageRiskActions $actions): JsonResponse
    {
        $this->gate->authorize('assess', $risk);

        try {
            $actions->add($risk, $request->string('title')->toString(), $request->filled('owner_id') ? $request->string('owner_id')->toString() : null, $request->filled('due_on') ? CarbonImmutable::createFromFormat('!Y-m-d', $request->string('due_on')->toString()) ?: null : null, $this->actor($request));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['owner_id' => $exception->getMessage()]);
        }

        return (new RiskResource($risk->refresh()->load(self::RELATIONS)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Move a treatment action on: planned, in progress, done or cancelled.
     */
    public function updateAction(RiskActionRequest $request, Risk $risk, RiskAction $action, ManageRiskActions $actions): RiskResource
    {
        $this->gate->authorize('assess', $risk);
        abort_unless($action->risk_id === $risk->id, Response::HTTP_NOT_FOUND);
        $actions->changeStatus($action, $request->enum('status', ActionStatus::class) ?? ActionStatus::Planned, $this->actor($request));

        return new RiskResource($risk->refresh()->load(self::RELATIONS));
    }

    private function save(SaveRiskRequest $request, SaveRisk $save, ?Risk $risk): Risk
    {
        $input = $request->validated();
        $text = fn (mixed $value): ?string => is_string($value) ? $value : null;
        $value = fn (string $key, mixed $current): mixed => array_key_exists($key, $input) ? $input[$key] : $current;

        $ownerId = $text($value('owner_id', $risk?->owner_id));

        // As on screen: the owner is an active member of the organization.
        if ($ownerId === null || ! User::query()->whereKey($ownerId)->where('is_active', true)->whereHas('organizations', fn (Builder $query) => $query->whereKey($this->context->requireId()))->exists()) {
            throw ValidationException::withMessages(['owner_id' => __('risk::api.unknown_owner')]);
        }

        return $save->handle($risk, [
            'title' => $text($value('title', $risk?->title)) ?? '',
            'description' => $text($value('description', $risk?->description)),
            'category' => RiskCategory::from($text($value('category', $risk?->category->value)) ?? RiskCategory::Operational->value),
            'owner_id' => $ownerId,
            'status' => RiskStatus::from($text($value('status', $risk?->status->value)) ?? RiskStatus::Open->value),
        ]);
    }

    private function score(Request $request, string $key): ?int
    {
        $value = $request->input($key);

        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $user;
    }
}
