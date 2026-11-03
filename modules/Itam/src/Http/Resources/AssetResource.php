<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Itam\Domain\Models\Asset;

/**
 * @mixin Asset
 */
final class AssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->whenLoaded('category', fn (): array => ['id' => $this->category->id, 'key' => $this->category->key, 'name' => $this->category->name]),
            'asset_tag' => $this->asset_tag,
            'name' => $this->name,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'serial_number' => $this->serial_number,
            'status' => $this->status->value,
            'holder' => $this->whenLoaded('holder', fn (): ?array => $this->holder !== null ? ['id' => $this->holder->id, 'name' => $this->holder->name] : null),
            'location' => $this->whenLoaded('location', fn (): ?array => $this->location !== null ? ['id' => $this->location->id, 'name' => $this->location->name] : null),
            'supplier_id' => $this->supplier_id,
            'contract_id' => $this->contract_id,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'purchase_cost' => $this->purchase_cost,
            'currency' => $this->currency,
            'order_number' => $this->order_number,
            'invoice_number' => $this->invoice_number,
            'warranty_ends_on' => $this->warranty_ends_on?->toDateString(),
            'replace_on' => $this->replace_on?->toDateString(),
            'notes' => $this->notes,
            'properties' => (object) $this->properties,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
