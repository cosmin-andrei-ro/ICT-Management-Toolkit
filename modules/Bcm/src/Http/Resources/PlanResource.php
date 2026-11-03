<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bcm\Domain\Models\PlanVersion;
use Modules\Bcm\Domain\Models\Step;
use Modules\Bcm\Domain\Models\TeamMember;

/**
 * A continuity (BCP) or recovery (DRP) plan and, on show, the version in force.
 *
 * @mixin Plan
 */
final class PlanResource extends JsonResource
{
    private ?PlanVersion $inForce = null;

    public function withVersionInForce(?PlanVersion $version): self
    {
        $this->inForce = $version;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $version = $this->inForce;

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'description' => $this->description,
            'owner' => $this->whenLoaded('owner', fn (): ?array => $this->owner !== null ? ['id' => $this->owner->id, 'name' => $this->owner->name] : null),
            'deputy' => $this->whenLoaded('deputy', fn (): ?array => $this->deputy !== null ? ['id' => $this->deputy->id, 'name' => $this->deputy->name] : null),
            'status' => $this->status,
            'approved_version' => $this->approved_version,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'next_review_on' => $this->next_review_on?->toDateString(),
            'last_exercised_on' => $this->last_exercised_on?->toDateString(),
            /** The approved version: what to do when the plan is activated. */
            'in_force' => $this->when($version !== null, fn (): array => [
                'version' => $version?->version,
                'objective' => $version?->objective,
                'scenarios' => $version->scenarios ?? [],
                'activation_criteria' => $version?->activation_criteria,
                'activation_authority' => $version?->activation_authority,
                'communication' => $version?->communication,
                'strategy' => $version?->strategy,
                'assumptions' => $version?->assumptions,
                'process_ids' => $version?->processIds() ?? [],
                'estimated_recovery_minutes' => $version?->estimatedRecoveryMinutes(),
                'team' => $version?->team->map(fn (TeamMember $member): array => [
                    'role' => $member->role, 'user_id' => $member->user_id, 'name' => $member->user->name ?? $member->contact_name,
                    'phone' => $member->contact_phone, 'email' => $member->contact_email, 'is_deputy' => $member->is_deputy, 'responsibilities' => $member->responsibilities,
                ])->all() ?? [],
                'steps' => $version?->steps->map(fn (Step $step): array => [
                    'position' => $step->position, 'phase' => $step->phase->value, 'action' => $step->action, 'responsible' => $step->responsibleUser->name ?? $step->responsible,
                    'estimated_minutes' => $step->estimated_minutes, 'configuration_item_id' => $step->configuration_item_id, 'notes' => $step->notes,
                ])->all() ?? [],
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
