<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeAssetStatusRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** Back in stock, in or out of repair, retired or lost. Handing out: the assign endpoint; disposal: on screen. */
            'status' => ['required', Rule::in(['in_stock', 'deployed', 'in_repair', 'retired', 'lost'])],
            'location_id' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
