<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Controllers\Api;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Core\Domain\Models\User;
use Modules\Core\Http\Api\ApiQuery;
use Modules\Itam\Application\Actions\ManageLicenseSeats;
use Modules\Itam\Application\Actions\SaveLicense;
use Modules\Itam\Domain\Exceptions\LicenseSeatsInUse;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\License;
use Modules\Itam\Domain\Models\LicenseAssignment;
use Modules\Itam\Http\Requests\Api\LicenseSeatRequest;
use Modules\Itam\Http\Requests\Api\SaveLicenseRequest;
use Modules\Itam\Http\Resources\LicenseResource;

/**
 * Licences and their seats through the API (ADR 0062). The key can be set or
 * removed, never read.
 */
final readonly class LicenseController
{
    public function __construct(private Gate $gate) {}

    /**
     * List licences, with the number of seats in use.
     *
     * Filters: `filter[product_id]`, `filter[model]`, `filter[expires_before]` (Y-m-d),
     * `filter[search]` (name). Sorts: `name`, `expires_on`, `created_at`; by name by default.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', License::class);

        $licenses = ApiQuery::paginate($request, License::query()->with('product')->withCount('assignments'), [
            'product_id' => fn (Builder $query, string $value) => $query->where('product_id', $value),
            'model' => fn (Builder $query, string $value) => $query->where('model', $value),
            'expires_before' => fn (Builder $query, string $value) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
                ? $query->whereNotNull('expires_on')->where('expires_on', '<', $value)
                : throw ValidationException::withMessages(['filter' => __('itam::api.date')]),
            'search' => fn (Builder $query, string $value) => $query->where('name', 'ilike', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value).'%'),
        ], ['name' => 'name', 'expires_on' => 'expires_on', 'created_at' => 'created_at'], 'name');

        return LicenseResource::collection($licenses);
    }

    /**
     * Show a licence and who holds its seats.
     */
    public function show(License $license): LicenseResource
    {
        $this->gate->authorize('view', $license);

        return new LicenseResource($license->load(['product', 'assignments.user', 'assignments.asset'])->loadCount('assignments'));
    }

    /**
     * Register a licence.
     */
    public function store(SaveLicenseRequest $request, SaveLicense $save): JsonResponse
    {
        $this->gate->authorize('create', License::class);

        return (new LicenseResource($this->save($request, $save, null)->load('product')->loadCount('assignments')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Change a licence: only the fields sent change.
     */
    public function update(SaveLicenseRequest $request, License $license, SaveLicense $save): LicenseResource
    {
        $this->gate->authorize('update', $license);

        return new LicenseResource($this->save($request, $save, $license)->load('product')->loadCount('assignments'));
    }

    /**
     * Give a seat to a person (per-user licences) or a device (per-device licences).
     */
    public function addSeat(LicenseSeatRequest $request, License $license, ManageLicenseSeats $seats): LicenseResource
    {
        $this->gate->authorize('assign', $license);
        $actor = $this->actor($request);

        try {
            if ($request->filled('user_id')) {
                $person = User::query()->find($request->string('user_id')->toString()) ?? throw new InvalidArgumentException(__('itam::api.unknown_person'));
                $seats->giveToPerson($license, $person, $actor);
            } else {
                $asset = Asset::query()->find($request->string('asset_id')->toString()) ?? throw new InvalidArgumentException(__('itam::api.unknown_asset'));
                $seats->giveToDevice($license, $asset, $actor);
            }
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([$request->filled('user_id') ? 'user_id' : 'asset_id' => $exception->getMessage()]);
        }

        return new LicenseResource($license->load(['product', 'assignments.user', 'assignments.asset'])->loadCount('assignments'));
    }

    /**
     * Free a seat.
     */
    public function removeSeat(Request $request, License $license, LicenseAssignment $seat, ManageLicenseSeats $seats): Response
    {
        $this->gate->authorize('assign', $license);
        abort_unless($seat->license_id === $license->id, Response::HTTP_NOT_FOUND);
        $seats->release($seat, $this->actor($request));

        return response()->noContent();
    }

    private function save(SaveLicenseRequest $request, SaveLicense $save, ?License $license): License
    {
        $input = $request->validated();
        $text = fn (mixed $value): ?string => is_string($value) ? $value : (is_numeric($value) ? (string) $value : null);
        $date = fn (?DateTimeInterface $value): ?string => $value?->format('Y-m-d');
        // PATCH: what was not sent keeps its value.
        $value = fn (string $key, mixed $current): ?string => $text(array_key_exists($key, $input) ? $input[$key] : $current);
        $productId = $value('product_id', $license?->product_id);
        $productName = $text($input['product_name'] ?? null);

        $fields = [
            'name' => $value('name', $license?->name) ?? '',
            'model' => $value('model', $license?->model->value) ?? '',
            'quantity' => $value('quantity', $license?->quantity),
            'supplier_id' => $value('supplier_id', $license?->supplier_id),
            'contract_id' => $value('contract_id', $license?->contract_id),
            'purchase_date' => $value('purchase_date', $date($license?->purchase_date)),
            'cost' => $value('cost', $license?->cost),
            'currency' => $value('currency', $license?->currency),
            'starts_on' => $value('starts_on', $date($license?->starts_on)),
            'expires_on' => $value('expires_on', $date($license?->expires_on)),
            'notes' => $value('notes', $license?->notes),
        ];

        try {
            return $save->handle($license, $productId ?? '', $productName, $fields, $text($input['license_key'] ?? null), (bool) ($input['remove_key'] ?? false));
        } catch (LicenseSeatsInUse) {
            throw ValidationException::withMessages(['model' => __('itam::licenses.ui.model_has_seats')]);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['product_id' => __('itam::licenses.ui.invalid_reference')]);
        }
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $user;
    }
}
