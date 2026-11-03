<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Itam\Domain\Enums\LicenseModel;

/**
 * Creating (POST) or changing (PATCH: only the fields sent) a licence. The key
 * can be set or removed, never read.
 */
final class SaveLicenseRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'product_id' => [$creating ? 'required_without:product_name' : 'sometimes', 'nullable', 'uuid'],
            /** A new product of this name, when no product_id is given. */
            'product_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'model' => [$creating ? 'required' : 'sometimes', Rule::enum(LicenseModel::class)],
            'quantity' => ['sometimes', 'nullable', 'integer', 'between:1,1000000'],
            /** Write-only. */
            'license_key' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'remove_key' => ['sometimes', 'boolean'],
            'supplier_id' => ['sometimes', 'nullable', 'uuid'],
            'contract_id' => ['sometimes', 'nullable', 'uuid'],
            'purchase_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'cost' => ['sometimes', 'nullable', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'currency' => ['required_with:cost', 'nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'starts_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'expires_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
