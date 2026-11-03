<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Controllers\Api;

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
use Modules\Audit\Application\Actions\ManageEvidence;
use Modules\Audit\Application\Actions\MapControl;
use Modules\Audit\Application\Actions\RecordControlTest;
use Modules\Audit\Application\Actions\SaveControl;
use Modules\Audit\Domain\Enums\ControlStatus;
use Modules\Audit\Domain\Enums\TestResult;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\Evidence;
use Modules\Audit\Http\Requests\Api\ControlEvidenceRequest;
use Modules\Audit\Http\Requests\Api\ControlImplementationRequest;
use Modules\Audit\Http\Requests\Api\ControlTestRequest;
use Modules\Audit\Http\Requests\Api\SaveControlRequest;
use Modules\Audit\Http\Resources\ControlResource;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Api\ApiQuery;

/**
 * Controls through the API (ADR 0063): compliance managers create, change and
 * map them; a control's owner (or a compliance manager) records how it is
 * implemented, links evidence and records tests.
 */
final readonly class ControlController
{
    private const array RELATIONS = ['owner', 'requirements.framework', 'evidence', 'tests'];

    public function __construct(private Gate $gate, private OrganizationContext $context) {}

    /**
     * List controls.
     *
     * Filters: `filter[status]`, `filter[owner_id]`, `filter[framework]` (key: controls mapped to it),
     * `filter[test_overdue]` (true), `filter[search]` (name). Sorts: `reference`, `name`,
     * `next_test_on`, `updated_at`; by reference by default.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', Control::class);

        $controls = ApiQuery::paginate($request, Control::query()->with('owner'), [
            'status' => fn (Builder $query, string $value) => $query->where('status', $value),
            'owner_id' => fn (Builder $query, string $value) => Str::isUuid($value) ? $query->where('owner_id', $value) : $query->whereRaw('false'),
            'framework' => fn (Builder $query, string $value) => $query->whereHas('requirements.framework', fn (Builder $frameworks) => $frameworks->where('key', $value)),
            'test_overdue' => fn (Builder $query, string $value) => $value === 'true'
                ? $query->whereNotNull('next_test_on')->where('next_test_on', '<', CarbonImmutable::today()->toDateString())->where('status', '!=', ControlStatus::Retired->value)
                : $query,
            'search' => fn (Builder $query, string $value) => $query->where('name', 'ilike', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value).'%'),
        ], ['reference' => 'number', 'name' => 'name', 'next_test_on' => 'next_test_on', 'updated_at' => 'updated_at'], 'reference');

        return ControlResource::collection($controls);
    }

    /**
     * Show a control, the requirements it meets, its evidence and its tests.
     */
    public function show(Control $control): ControlResource
    {
        $this->gate->authorize('view', $control);

        return new ControlResource($control->load(self::RELATIONS));
    }

    /**
     * Create a control.
     */
    public function store(SaveControlRequest $request, SaveControl $save): JsonResponse
    {
        $this->gate->authorize('create', Control::class);

        return (new ControlResource($this->save($request, $save, null)->load(self::RELATIONS)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Change a control: only the fields sent.
     */
    public function update(SaveControlRequest $request, Control $control, SaveControl $save): ControlResource
    {
        $this->gate->authorize('update', $control);

        return new ControlResource($this->save($request, $save, $control)->load(self::RELATIONS));
    }

    /**
     * Record how far the control is implemented (its owner may).
     */
    public function implementation(ControlImplementationRequest $request, Control $control, SaveControl $save): ControlResource
    {
        $this->gate->authorize('implement', $control);
        $save->implementation($control, $request->enum('status', ControlStatus::class) ?? $control->status, $request->filled('implementation_notes') ? $request->string('implementation_notes')->toString() : null);

        return new ControlResource($control->refresh()->load(self::RELATIONS));
    }

    /**
     * Map the control to a requirement it meets.
     */
    public function mapRequirement(Request $request, Control $control, MapControl $map): ControlResource
    {
        $this->gate->authorize('update', $control);
        $request->validate(['requirement_id' => ['required', 'uuid']]);

        try {
            $map->add($control, $request->string('requirement_id')->toString(), $this->actor($request));
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['requirement_id' => __('audit::api.unknown_requirement')]);
        }

        return new ControlResource($control->refresh()->load(self::RELATIONS));
    }

    /**
     * Unmap a requirement.
     */
    public function unmapRequirement(Request $request, Control $control, string $requirement, MapControl $map): Response
    {
        $this->gate->authorize('update', $control);
        $map->remove($control, $requirement, $this->actor($request));

        return response()->noContent();
    }

    /**
     * Link a record of the toolkit as evidence (files are uploaded on screen).
     */
    public function linkEvidence(ControlEvidenceRequest $request, Control $control, ManageEvidence $evidence): JsonResponse
    {
        $this->gate->authorize('implement', $control);

        try {
            $evidence->link($control, $request->string('type')->toString(), $request->string('id')->toString(), $request->filled('description') ? $request->string('description')->toString() : null, $this->actor($request));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['id' => $exception->getMessage()]);
        }

        return (new ControlResource($control->refresh()->load(self::RELATIONS)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Remove a piece of evidence (kept in the history).
     */
    public function removeEvidence(Control $control, Evidence $evidence, ManageEvidence $manage): Response
    {
        $this->gate->authorize('implement', $control);
        abort_unless($evidence->control_id === $control->id, Response::HTTP_NOT_FOUND);
        $manage->remove($evidence);

        return response()->noContent();
    }

    /**
     * Record a test of the control, once done; the next one is scheduled from it.
     */
    public function recordTest(ControlTestRequest $request, Control $control, RecordControlTest $record): JsonResponse
    {
        $this->gate->authorize('implement', $control);
        $testedOn = CarbonImmutable::createFromFormat('!Y-m-d', $request->string('tested_on')->toString()) ?: CarbonImmutable::today();

        try {
            $record->handle($control, $testedOn, $request->enum('result', TestResult::class) ?? TestResult::Effective, $request->string('method')->toString(), $request->filled('notes') ? $request->string('notes')->toString() : null, null, $this->actor($request));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['tested_on' => $exception->getMessage()]);
        }

        return (new ControlResource($control->refresh()->load(self::RELATIONS)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    private function save(SaveControlRequest $request, SaveControl $save, ?Control $control): Control
    {
        $input = $request->validated();
        $text = fn (mixed $value): ?string => is_string($value) ? $value : null;
        $value = fn (string $key, mixed $current): mixed => array_key_exists($key, $input) ? $input[$key] : $current;
        $ownerId = $text($value('owner_id', $control?->owner_id));

        if ($ownerId !== null && ! User::query()->whereKey($ownerId)->where('is_active', true)->whereHas('organizations', fn (Builder $query) => $query->whereKey($this->context->requireId()))->exists()) {
            throw ValidationException::withMessages(['owner_id' => __('audit::api.unknown_owner')]);
        }

        $interval = $value('test_interval_months', $control?->test_interval_months);

        try {
            return $save->handle($control, [
                'name' => $text($value('name', $control?->name)) ?? '',
                'description' => $text($value('description', $control?->description)),
                'owner_id' => $ownerId,
                'status' => ControlStatus::from($text($value('status', $control?->status->value)) ?? ControlStatus::Planned->value),
                'implementation_notes' => $text($value('implementation_notes', $control?->implementation_notes)),
                'test_interval_months' => is_int($interval) ? $interval : null,
            ]);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['name' => $exception->getMessage()]);
        }
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $user;
    }
}
