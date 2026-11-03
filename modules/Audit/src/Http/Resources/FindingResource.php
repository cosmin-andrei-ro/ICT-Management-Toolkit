<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Audit\Domain\Models\Finding;
use Modules\Audit\Domain\Models\FindingAction;

/**
 * @mixin Finding
 */
final class FindingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->code(),
            'title' => $this->title,
            'description' => $this->description,
            'severity' => $this->severity->value,
            'status' => $this->status->value,
            'owner' => $this->whenLoaded('owner', fn (): ?array => $this->owner !== null ? ['id' => $this->owner->id, 'name' => $this->owner->name] : null),
            'due_on' => $this->due_on?->toDateString(),
            'overdue' => $this->isOverdue(CarbonImmutable::today()),
            'audit_id' => $this->audit_id,
            'control_id' => $this->control_id,
            'requirement_id' => $this->requirement_id,
            'root_cause' => $this->root_cause,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'actions' => $this->whenLoaded('actions', fn (): array => $this->actions->map(fn (FindingAction $action): array => [
                'id' => $action->id, 'title' => $action->title, 'owner_id' => $action->owner_id, 'due_on' => $action->due_on?->toDateString(), 'status' => $action->status->value,
            ])->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
