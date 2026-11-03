<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Http\Controllers\Api;

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
use Modules\Cmdb\Application\Actions\SaveConfigurationItem;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Cmdb\Http\Requests\Api\SaveConfigurationItemRequest;
use Modules\Cmdb\Http\Resources\ConfigurationItemResource;
use Modules\Core\Http\Api\ApiQuery;

/**
 * Configuration items through the API (ADR 0062), with the rules of the
 * screens: the same action, the type's attribute schema, the policy.
 */
final readonly class ConfigurationItemController
{
    public function __construct(private Gate $gate) {}

    /**
     * List configuration items.
     *
     * Filters: `filter[type]` (key or id), `filter[status]`, `filter[environment]`,
     * `filter[criticality]`, `filter[owner_id]`, `filter[team_id]`, `filter[search]`
     * (name or identifier). Sorts: `name`, `identifier`, `criticality`, `created_at`,
     * `updated_at`; by name by default.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', ConfigurationItem::class);
        $exact = fn (string $column): Closure => fn (Builder $query, string $value) => $query->where($column, $value);
        $uuid = fn (string $column): Closure => fn (Builder $query, string $value) => Str::isUuid($value) ? $query->where($column, $value) : $query->whereRaw('false');

        $items = ApiQuery::paginate($request, ConfigurationItem::query()->with(['type', 'owner', 'team']), [
            'type' => fn (Builder $query, string $value) => $query->whereIn('type_id', CiType::query()->select('id')->where(fn (Builder $types) => Str::isUuid($value) ? $types->whereKey($value) : $types->where('key', $value))),
            'status' => $exact('status'),
            'environment' => $exact('environment'),
            'criticality' => $exact('criticality'),
            'owner_id' => $uuid('owner_id'),
            'team_id' => $uuid('team_id'),
            'search' => fn (Builder $query, string $value) => $query->where(fn (Builder $query) => $query
                ->where('name', 'ilike', '%'.self::like($value).'%')->orWhere('identifier', 'ilike', '%'.self::like($value).'%')),
        ], ['name' => 'name', 'identifier' => 'identifier', 'criticality' => 'criticality', 'created_at' => 'created_at', 'updated_at' => 'updated_at'], 'name');

        return ConfigurationItemResource::collection($items);
    }

    /**
     * Show a configuration item, with its relationships in both directions.
     */
    public function show(ConfigurationItem $item): ConfigurationItemResource
    {
        $this->gate->authorize('view', $item);

        return new ConfigurationItemResource($item->load(['type', 'owner', 'team', 'outgoing.target', 'incoming.source']));
    }

    /**
     * Create a configuration item.
     */
    public function store(SaveConfigurationItemRequest $request, SaveConfigurationItem $save): JsonResponse
    {
        $this->gate->authorize('create', ConfigurationItem::class);
        $item = $this->save($request, $save, null);

        return (new ConfigurationItemResource($item->load(['type', 'owner', 'team'])))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Change a configuration item: only the fields sent change; `properties` are merged by key.
     */
    public function update(SaveConfigurationItemRequest $request, ConfigurationItem $item, SaveConfigurationItem $save): ConfigurationItemResource
    {
        $this->gate->authorize('update', $item);

        return new ConfigurationItemResource($this->save($request, $save, $item)->load(['type', 'owner', 'team']));
    }

    /**
     * Delete a configuration item (it stays in the audit trail and can be restored by an administrator).
     */
    public function destroy(ConfigurationItem $item): Response
    {
        $this->gate->authorize('delete', $item);
        $item->delete();

        return response()->noContent();
    }

    private function save(SaveConfigurationItemRequest $request, SaveConfigurationItem $save, ?ConfigurationItem $item): ConfigurationItem
    {
        $input = $request->validated();
        // PATCH: what was not sent keeps its value.
        $value = fn (string $key, mixed $current): mixed => array_key_exists($key, $input) ? $input[$key] : $current;
        $text = fn (mixed $value): ?string => is_string($value) ? $value : null;
        $number = fn (mixed $value): ?int => is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;

        $fields = [
            'name' => $text($value('name', $item?->name)) ?? '',
            'identifier' => $text($value('identifier', $item?->identifier)),
            'status' => $text($value('status', $item?->status->value)),
            'environment' => $text($value('environment', $item?->environment?->value)),
            'criticality' => $text($value('criticality', $item?->criticality->value)),
            'owner_id' => $text($value('owner_id', $item?->owner_id)),
            'team_id' => $text($value('team_id', $item?->team_id)),
            'description' => $text($value('description', $item?->description)),
            'recovery_time_minutes' => $number($value('recovery_time_minutes', $item?->recovery_time_minutes)),
            'recovery_point_minutes' => $number($value('recovery_point_minutes', $item?->recovery_point_minutes)),
        ];
        $sent = $input['properties'] ?? [];
        $properties = [];

        foreach (is_array($sent) ? $sent : [] as $key => $property) {
            if (is_string($key)) {
                $properties[$key] = $property;
            }
        }
        $typeId = $text($input['type_id'] ?? null) ?? $item?->type_id;
        // Scoped lookup: a type of another organization is not found.
        $type = $typeId !== null ? CiType::query()->find($typeId) : null;

        if ($type === null) {
            throw ValidationException::withMessages(['type_id' => __('cmdb::api.unknown_type')]);
        }

        try {
            // Properties are merged by key, unless the item changes type: then only what was sent counts.
            return $save->handle($item, $type, $fields, $item !== null && $type->id === $item->type_id ? [...$item->properties, ...$properties] : $properties);
        } catch (ValidationException $exception) {
            $errors = [];

            foreach ($exception->errors() as $key => $messages) {
                $errors['properties.'.$key] = $messages;
            }

            throw ValidationException::withMessages($errors);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['owner_id' => __('cmdb::items.ui.invalid_owner_or_team')]);
        }
    }

    private static function like(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
