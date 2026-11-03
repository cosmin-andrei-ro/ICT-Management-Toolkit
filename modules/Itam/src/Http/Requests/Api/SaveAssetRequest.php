<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Requests\Api;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Itam\Domain\Models\Asset;

/**
 * Creating (POST) or changing (PATCH: only the fields sent) an asset. The
 * status is chosen at creation only (ordered or in stock); afterwards it
 * changes through the assign and status endpoints, as on screen.
 */
final class SaveAssetRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $asset = $this->route('asset');
        $assetId = $asset instanceof Asset ? $asset->id : null;
        $unique = fn (string $column): Closure => function (string $attribute, mixed $value, Closure $fail) use ($column, $assetId): void {
            // Case-insensitive, like the database's unique indexes.
            if (is_string($value) && trim($value) !== '' && Asset::query()->whereRaw($column === 'asset_tag' ? 'lower(asset_tag) = ?' : 'lower(serial_number) = ?', [mb_strtolower(trim($value))])->when($assetId !== null, fn ($query) => $query->whereKeyNot($assetId))->exists()) {
                $fail(__('itam::assets.ui.taken'));
            }
        };

        return [
            'category_id' => [$creating ? 'required' : 'sometimes', 'uuid'],
            'asset_tag' => ['sometimes', 'nullable', 'string', 'max:64', $unique('asset_tag')],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'manufacturer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'serial_number' => ['sometimes', 'nullable', 'string', 'max:255', $unique('serial_number')],
            'status' => $creating ? ['sometimes', Rule::in(['ordered', 'in_stock'])] : ['prohibited'],
            'location_id' => ['sometimes', 'nullable', 'uuid'],
            'supplier_id' => ['sometimes', 'nullable', 'uuid'],
            'contract_id' => ['sometimes', 'nullable', 'uuid'],
            'purchase_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'purchase_cost' => ['sometimes', 'nullable', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'currency' => ['required_with:purchase_cost', 'nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'order_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'invoice_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'warranty_ends_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'replace_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'properties' => ['sometimes', 'array'],
        ];
    }

    public function messages(): array
    {
        return ['status.prohibited' => __('itam::api.status_endpoint')];
    }
}
