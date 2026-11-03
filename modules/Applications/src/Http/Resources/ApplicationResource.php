<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Applications\Domain\Models\Application;

/**
 * An application of the portfolio and the state of its documentation.
 *
 * @mixin Application
 */
final class ApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $person = fn (?object $user): ?array => $user !== null && isset($user->id, $user->name) ? ['id' => $user->id, 'name' => $user->name] : null;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'department' => $this->whenLoaded('department', fn (): ?array => $this->department !== null ? ['id' => $this->department->id, 'name' => $this->department->name] : null),
            'department_email' => $this->department_email,
            'business_owner' => $this->whenLoaded('businessOwner', fn (): ?array => $person($this->businessOwner)),
            'technical_owner' => $this->whenLoaded('technicalOwner', fn (): ?array => $person($this->technicalOwner)),
            'deputy' => $this->whenLoaded('deputy', fn (): ?array => $person($this->deputy)),
            /** Keys of the editable lists (Administration → Application portfolio). */
            'status' => $this->status,
            'criticality' => $this->criticality,
            /** The version in force of the documentation sheet, e.g. "2.1"; null until one is approved. */
            'documentation_version' => $this->current_version,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'next_review_on' => $this->next_review_on?->toDateString(),
            'configuration_item_id' => $this->configuration_item_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
