<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\ControlTest;
use Modules\Audit\Domain\Models\Evidence;
use Modules\Audit\Domain\Models\Requirement;

/**
 * @mixin Control
 */
final class ControlResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->code(),
            'name' => $this->name,
            'description' => $this->description,
            'owner' => $this->whenLoaded('owner', fn (): ?array => $this->owner !== null ? ['id' => $this->owner->id, 'name' => $this->owner->name] : null),
            'status' => $this->status->value,
            'implementation_notes' => $this->implementation_notes,
            'test_interval_months' => $this->test_interval_months,
            'last_tested_on' => $this->last_tested_on?->toDateString(),
            'next_test_on' => $this->next_test_on?->toDateString(),
            'test_overdue' => $this->isTestOverdue(CarbonImmutable::today()),
            'requirements' => $this->whenLoaded('requirements', fn (): array => $this->requirements->map(fn (Requirement $requirement): array => [
                'id' => $requirement->id, 'framework' => $requirement->framework->key, 'reference' => $requirement->reference, 'title' => $requirement->title,
            ])->all()),
            /** Links to records of the toolkit, and uploaded files (listed, downloaded on screen). */
            'evidence' => $this->whenLoaded('evidence', fn (): array => $this->evidence->map(fn (Evidence $evidence): array => [
                'id' => $evidence->id, 'kind' => $evidence->kind->value, 'title' => $evidence->title, 'linked_type' => $evidence->linked_type, 'linked_id' => $evidence->linked_id,
                'valid_until' => $evidence->valid_until?->toDateString(),
            ])->all()),
            'tests' => $this->whenLoaded('tests', fn (): array => $this->tests->map(fn (ControlTest $test): array => [
                'id' => $test->id, 'tested_on' => $test->tested_on->toDateString(), 'result' => $test->result->value, 'method' => $test->method, 'notes' => $test->notes,
            ])->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
