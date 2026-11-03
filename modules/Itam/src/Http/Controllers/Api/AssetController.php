<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Controllers\Api;

use Closure;
use DateTimeInterface;
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
use Modules\Core\Http\Api\ApiQuery;
use Modules\Itam\Application\Actions\AssignAsset;
use Modules\Itam\Application\Actions\ChangeAssetStatus;
use Modules\Itam\Application\Actions\SaveAsset;
use Modules\Itam\Domain\Enums\AssetStatus;
use Modules\Itam\Domain\Exceptions\AssetTransitionNotAllowed;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\Category;
use Modules\Itam\Http\Requests\Api\AssignAssetRequest;
use Modules\Itam\Http\Requests\Api\ChangeAssetStatusRequest;
use Modules\Itam\Http\Requests\Api\SaveAssetRequest;
use Modules\Itam\Http\Resources\AssetResource;

/**
 * Assets through the API (ADR 0062), with the rules of the screens: details
 * are saved by SaveAsset; who holds an asset and its status change only
 * through the hand-over and status actions, which keep the assignment history.
 */
final readonly class AssetController
{
    private const array RELATIONS = ['category', 'holder', 'location'];

    public function __construct(private Gate $gate) {}

    /**
     * List assets.
     *
     * Filters: `filter[category]` (key or id), `filter[status]`, `filter[holder_id]`,
     * `filter[location_id]`, `filter[search]` (tag, name, serial number). Sorts:
     * `asset_tag`, `name`, `created_at`, `updated_at`, `warranty_ends_on`; by tag by default.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', Asset::class);
        $uuid = fn (string $column): Closure => fn (Builder $query, string $value) => Str::isUuid($value) ? $query->where($column, $value) : $query->whereRaw('false');
        $like = fn (string $value): string => '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value).'%';

        $assets = ApiQuery::paginate($request, Asset::query()->with(self::RELATIONS), [
            'category' => fn (Builder $query, string $value) => $query->whereIn('category_id', Category::query()->select('id')->where(fn (Builder $categories) => Str::isUuid($value) ? $categories->whereKey($value) : $categories->where('key', $value))),
            'status' => fn (Builder $query, string $value) => $query->where('status', $value),
            'holder_id' => $uuid('assigned_to_id'),
            'location_id' => $uuid('location_id'),
            'search' => fn (Builder $query, string $value) => $query->where(fn (Builder $query) => $query
                ->where('asset_tag', 'ilike', $like($value))->orWhere('name', 'ilike', $like($value))->orWhere('serial_number', 'ilike', $like($value))),
        ], ['asset_tag' => 'asset_tag', 'name' => 'name', 'created_at' => 'created_at', 'updated_at' => 'updated_at', 'warranty_ends_on' => 'warranty_ends_on'], 'asset_tag');

        return AssetResource::collection($assets);
    }

    /**
     * Show an asset.
     */
    public function show(Asset $asset): AssetResource
    {
        $this->gate->authorize('view', $asset);

        return new AssetResource($asset->load(self::RELATIONS));
    }

    /**
     * Register an asset, ordered or in stock.
     */
    public function store(SaveAssetRequest $request, SaveAsset $save): JsonResponse
    {
        $this->gate->authorize('create', Asset::class);

        return (new AssetResource($this->save($request, $save, null)->load(self::RELATIONS)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Change an asset's details: only the fields sent change; `properties` are merged by key.
     */
    public function update(SaveAssetRequest $request, Asset $asset, SaveAsset $save): AssetResource
    {
        $this->gate->authorize('update', $asset);

        return new AssetResource($this->save($request, $save, $asset)->load(self::RELATIONS));
    }

    /**
     * Hand the asset over to a person of the organization (it becomes deployed).
     */
    public function assign(AssignAssetRequest $request, Asset $asset, AssignAsset $assign): AssetResource
    {
        $this->gate->authorize('assign', $asset);
        $holder = User::query()->find($request->string('user_id')->toString());

        if ($holder === null) {
            throw ValidationException::withMessages(['user_id' => __('itam::api.unknown_person')]);
        }

        try {
            $asset = $assign->handle($asset, $holder, $this->actor($request), $request->filled('location_id') ? $request->string('location_id')->toString() : null, $request->filled('notes') ? $request->string('notes')->toString() : null, $request->boolean('notify', true));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['user_id' => $exception->getMessage()]);
        } catch (AssetTransitionNotAllowed $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return new AssetResource($asset->load(self::RELATIONS));
    }

    /**
     * Change the status: back in stock (returned), in or out of repair, retired, lost.
     */
    public function status(ChangeAssetStatusRequest $request, Asset $asset, ChangeAssetStatus $change): AssetResource
    {
        $status = $request->enum('status', AssetStatus::class) ?? AssetStatus::InStock;
        $this->gate->authorize(in_array($status, [AssetStatus::InStock, AssetStatus::InRepair, AssetStatus::Deployed], true) ? 'assign' : 'update', $asset);

        try {
            $asset = $change->handle($asset, $status, $this->actor($request), $request->filled('location_id') ? $request->string('location_id')->toString() : null, $request->filled('notes') ? $request->string('notes')->toString() : null);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['location_id' => $exception->getMessage()]);
        } catch (AssetTransitionNotAllowed $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return new AssetResource($asset->load(self::RELATIONS));
    }

    private function save(SaveAssetRequest $request, SaveAsset $save, ?Asset $asset): Asset
    {
        $input = $request->validated();
        $text = fn (mixed $value): ?string => is_string($value) ? $value : (is_numeric($value) ? (string) $value : null);
        $date = fn (?DateTimeInterface $value): ?string => $value?->format('Y-m-d');
        // PATCH: what was not sent keeps its value.
        $value = fn (string $key, mixed $current): ?string => $text(array_key_exists($key, $input) ? $input[$key] : $current);

        $fields = [
            'asset_tag' => $value('asset_tag', $asset?->asset_tag),
            'name' => $value('name', $asset?->name),
            'manufacturer' => $value('manufacturer', $asset?->manufacturer),
            'model' => $value('model', $asset?->model),
            'serial_number' => $value('serial_number', $asset?->serial_number),
            'status' => $value('status', null),
            'location_id' => $value('location_id', $asset?->location_id),
            'supplier_id' => $value('supplier_id', $asset?->supplier_id),
            'contract_id' => $value('contract_id', $asset?->contract_id),
            'purchase_date' => $value('purchase_date', $date($asset?->purchase_date)),
            'purchase_cost' => $value('purchase_cost', $asset?->purchase_cost),
            'currency' => $value('currency', $asset?->currency),
            'order_number' => $value('order_number', $asset?->order_number),
            'invoice_number' => $value('invoice_number', $asset?->invoice_number),
            'warranty_ends_on' => $value('warranty_ends_on', $date($asset?->warranty_ends_on)),
            'replace_on' => $value('replace_on', $date($asset?->replace_on)),
            'notes' => $value('notes', $asset?->notes),
        ];
        $sent = $input['properties'] ?? [];
        $properties = [];

        foreach (is_array($sent) ? $sent : [] as $key => $property) {
            if (is_string($key)) {
                $properties[$key] = $property;
            }
        }
        $categoryId = $text($input['category_id'] ?? null) ?? $asset?->category_id;
        // Scoped lookup: a category of another organization is not found.
        $category = $categoryId !== null ? Category::query()->find($categoryId) : null;

        if ($category === null) {
            throw ValidationException::withMessages(['category_id' => __('itam::api.unknown_category')]);
        }

        try {
            return $save->handle($asset, $category, $fields, $asset !== null && $category->id === $asset->category_id ? [...$asset->properties, ...$properties] : $properties);
        } catch (ValidationException $exception) {
            $errors = [];

            foreach ($exception->errors() as $key => $messages) {
                $errors['properties.'.$key] = $messages;
            }

            throw ValidationException::withMessages($errors);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['location_id' => __('itam::assets.ui.invalid_reference')]);
        }
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        return $user;
    }
}
