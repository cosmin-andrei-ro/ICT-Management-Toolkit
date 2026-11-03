<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Bia\Domain\Models\BusinessProcess;

/**
 * A business process and the recovery objectives of its approved impact analysis.
 *
 * @mixin BusinessProcess
 */
final class ProcessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'department' => $this->whenLoaded('department', fn (): ?array => $this->department !== null ? ['id' => $this->department->id, 'name' => $this->department->name] : null),
            'owner' => $this->whenLoaded('owner', fn (): ?array => $this->owner !== null ? ['id' => $this->owner->id, 'name' => $this->owner->name] : null),
            'deputy' => $this->whenLoaded('deputy', fn (): ?array => $this->deputy !== null ? ['id' => $this->deputy->id, 'name' => $this->deputy->name] : null),
            'peak_periods' => $this->peak_periods,
            'status' => $this->status,
            /** From the approved impact analysis; null until one is approved. */
            'tier' => $this->tier?->value,
            'mtpd_minutes' => $this->mtpd_minutes,
            'rto_minutes' => $this->rto_minutes,
            'rpo_minutes' => $this->rpo_minutes,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'next_review_on' => $this->next_review_on?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
