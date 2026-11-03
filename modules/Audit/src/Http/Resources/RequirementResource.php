<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\Requirement;

/**
 * A requirement, its applicability (statement of applicability) and its coverage.
 *
 * @mixin Requirement
 */
final class RequirementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'title' => $this->title,
            'group' => $this->group,
            'description' => $this->description,
            'is_applicable' => $this->is_applicable,
            'justification' => $this->justification,
            /** undecided, not_applicable, not_covered, planned, partial or implemented. */
            'state' => $this->state()->value,
            'controls' => $this->whenLoaded('controls', fn (): array => $this->controls->map(fn (Control $control): array => ['id' => $control->id, 'reference' => $control->code(), 'name' => $control->name])->all()),
        ];
    }
}
