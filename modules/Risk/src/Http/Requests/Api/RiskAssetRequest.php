<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Risk\Domain\Enums\AssetType;

final class RiskAssetRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** application, configuration_item, process or supplier. */
            'type' => ['required', Rule::enum(AssetType::class)],
            'id' => ['required', 'uuid'],
        ];
    }
}
