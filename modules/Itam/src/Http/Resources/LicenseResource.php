<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Itam\Domain\Models\License;
use Modules\Itam\Domain\Models\LicenseAssignment;

/**
 * A licence. The key is never sent: `has_key` says whether one is stored, and
 * it is revealed only on screen, after a fresh second factor.
 *
 * @mixin License
 */
final class LicenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => $this->whenLoaded('product', fn (): array => ['id' => $this->product->id, 'name' => $this->product->name]),
            'name' => $this->name,
            'model' => $this->model->value,
            'quantity' => $this->quantity,
            'seats_used' => $this->whenCounted('assignments'),
            'has_key' => $this->license_key !== null,
            'supplier_id' => $this->supplier_id,
            'contract_id' => $this->contract_id,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'cost' => $this->cost,
            'currency' => $this->currency,
            'starts_on' => $this->starts_on?->toDateString(),
            'expires_on' => $this->expires_on?->toDateString(),
            'notes' => $this->notes,
            'seats' => $this->whenLoaded('assignments', fn (): array => $this->assignments->map(fn (LicenseAssignment $seat): array => [
                'id' => $seat->id,
                'user' => $seat->user !== null ? ['id' => $seat->user->id, 'name' => $seat->user->name] : null,
                'asset' => $seat->asset !== null ? ['id' => $seat->asset->id, 'asset_tag' => $seat->asset->asset_tag] : null,
                'assigned_at' => $seat->assigned_at->toIso8601String(),
            ])->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
