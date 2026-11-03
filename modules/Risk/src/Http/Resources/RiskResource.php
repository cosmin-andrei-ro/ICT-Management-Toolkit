<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Risk\Application\RiskAsset;
use Modules\Risk\Domain\Models\CatalogEntry;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\RiskAction;
use Modules\Risk\Domain\Models\RiskSettings;

/**
 * A risk with its scores and levels on the organization's method.
 *
 * @mixin Risk
 */
final class RiskResource extends JsonResource
{
    /** @var ?list<RiskAsset> What the risk concerns, resolved by the controller (show only). */
    private ?array $assets = null;

    /**
     * @param  list<RiskAsset>  $assets
     */
    public function withAssets(array $assets): self
    {
        $this->assets = $assets;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = RiskSettings::current();
        $inherent = $this->inherentScore();
        $residual = $this->residualScore();

        return [
            'id' => $this->id,
            'reference' => $this->code(),
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category->value,
            'status' => $this->status->value,
            'owner' => $this->whenLoaded('owner', fn (): ?array => $this->owner !== null ? ['id' => $this->owner->id, 'name' => $this->owner->name] : null),
            'inherent' => ['likelihood' => $this->inherent_likelihood, 'impact' => $this->inherent_impact, 'score' => $inherent, 'level' => $inherent !== null ? $settings->level($inherent)->value : null],
            'existing_controls' => $this->existing_controls,
            'treatment' => $this->treatment?->value,
            'treatment_plan' => $this->treatment_plan,
            'residual' => ['likelihood' => $this->residual_likelihood, 'impact' => $this->residual_impact, 'score' => $residual, 'level' => $residual !== null ? $settings->level($residual)->value : null],
            'accepted_until' => $this->isAccepted() ? $this->accepted_until?->toDateString() : null,
            'assessed_at' => $this->assessed_at?->toIso8601String(),
            'next_review_on' => $this->next_review_on?->toDateString(),
            'catalog_entries' => $this->whenLoaded('catalogEntries', fn (): array => $this->catalogEntries->map(fn (CatalogEntry $entry): array => ['id' => $entry->id, 'kind' => $entry->kind->value, 'name' => $entry->name])->all()),
            'assets' => $this->when($this->assets !== null, fn (): array => array_map(fn (RiskAsset $asset): array => ['type' => $asset->type->value, 'id' => $asset->id, 'name' => $asset->name], $this->assets ?? [])),
            'actions' => $this->whenLoaded('actions', fn (): array => $this->actions->map(fn (RiskAction $action): array => [
                'id' => $action->id, 'title' => $action->title, 'owner_id' => $action->owner_id, 'due_on' => $action->due_on?->toDateString(), 'status' => $action->status->value,
            ])->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
