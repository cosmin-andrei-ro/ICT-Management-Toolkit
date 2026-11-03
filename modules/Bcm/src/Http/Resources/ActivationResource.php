<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Bcm\Domain\Models\Activation;

/**
 * A real use of a plan.
 *
 * @mixin Activation
 */
final class ActivationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->plan_id,
            'scenario' => $this->scenario?->value,
            'reason' => $this->reason,
            'activated_at' => $this->activated_at->toIso8601String(),
            'restored_at' => $this->restored_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'rto_minutes' => $this->rto_minutes,
            'summary' => $this->summary,
            'lessons' => $this->lessons,
        ];
    }
}
