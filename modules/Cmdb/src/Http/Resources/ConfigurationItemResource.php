<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Cmdb\Domain\Models\Relationship;

/**
 * @mixin ConfigurationItem
 */
final class ConfigurationItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->whenLoaded('type', fn (): array => ['id' => $this->type->id, 'key' => $this->type->key, 'name' => $this->type->name]),
            'name' => $this->name,
            'identifier' => $this->identifier,
            'status' => $this->status->value,
            'environment' => $this->environment?->value,
            'criticality' => $this->criticality->value,
            'owner' => $this->whenLoaded('owner', fn (): ?array => $this->owner !== null ? ['id' => $this->owner->id, 'name' => $this->owner->name] : null),
            'team' => $this->whenLoaded('team', fn (): ?array => $this->team !== null ? ['id' => $this->team->id, 'name' => $this->team->name] : null),
            'description' => $this->description,
            'recovery_time_minutes' => $this->recovery_time_minutes,
            'recovery_point_minutes' => $this->recovery_point_minutes,
            'properties' => (object) $this->properties,
            /** Relationships where this item is the source: "this <type> target". */
            'outgoing' => $this->whenLoaded('outgoing', fn (): array => $this->outgoing->map(fn (Relationship $relationship): array => [
                'id' => $relationship->id, 'type' => $relationship->type->value, 'item' => ['id' => $relationship->target_id, 'name' => $relationship->target->name],
            ])->all()),
            /** Relationships where this item is the target: "source <type> this". */
            'incoming' => $this->whenLoaded('incoming', fn (): array => $this->incoming->map(fn (Relationship $relationship): array => [
                'id' => $relationship->id, 'type' => $relationship->type->value, 'item' => ['id' => $relationship->source_id, 'name' => $relationship->source->name],
            ])->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
