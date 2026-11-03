<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Bcm\Domain\Models\Exercise;

/**
 * @mixin Exercise
 */
final class ExerciseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->plan_id,
            'title' => $this->title,
            'type' => $this->type->value,
            'scenario' => $this->scenario?->value,
            'scheduled_on' => $this->scheduled_on->toDateString(),
            'status' => $this->status->value,
            'outcome' => $this->outcome?->value,
            'measured_minutes' => $this->measured_minutes,
            'rto_minutes' => $this->rto_minutes,
            'summary' => $this->summary,
            'lessons' => $this->lessons,
        ];
    }
}
