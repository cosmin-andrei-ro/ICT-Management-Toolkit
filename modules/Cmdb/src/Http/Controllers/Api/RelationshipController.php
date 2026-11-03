<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Http\Controllers\Api;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Cmdb\Application\Actions\ManageRelationships;
use Modules\Cmdb\Domain\Enums\RelationshipType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Cmdb\Domain\Models\Relationship;
use Modules\Cmdb\Http\Requests\Api\StoreRelationshipRequest;
use Modules\Cmdb\Http\Resources\RelationshipResource;

/**
 * Relationships between configuration items (ADR 0062): "source <type> target".
 */
final readonly class RelationshipController
{
    public function __construct(private Gate $gate) {}

    /**
     * Relate two items; an identical relationship that already exists is returned as it is.
     */
    public function store(StoreRelationshipRequest $request, ManageRelationships $relationships): JsonResponse
    {
        // Scoped lookups: items of another organization are not found.
        $source = ConfigurationItem::query()->find($request->string('source_id')->toString());
        $target = ConfigurationItem::query()->find($request->string('target_id')->toString());

        if ($source === null || $target === null) {
            throw ValidationException::withMessages([$source === null ? 'source_id' : 'target_id' => __('cmdb::api.unknown_item')]);
        }

        $this->gate->authorize('update', $source);

        try {
            $relationship = $relationships->add($source, $request->enum('type', RelationshipType::class) ?? RelationshipType::DependsOn, $target, $request->string('description')->toString());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['target_id' => $exception->getMessage()]);
        }

        return (new RelationshipResource($relationship))->response()->setStatusCode($relationship->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    /**
     * Remove a relationship.
     */
    public function destroy(Relationship $relationship, ManageRelationships $relationships): Response
    {
        $source = ConfigurationItem::query()->findOrFail($relationship->source_id);
        $this->gate->authorize('update', $source);
        $relationships->remove($relationship);

        return response()->noContent();
    }
}
