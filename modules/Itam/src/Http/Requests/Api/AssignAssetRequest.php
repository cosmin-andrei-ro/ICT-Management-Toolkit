<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class AssignAssetRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid'],
            'location_id' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string', 'max:1000'],
            /** E-mail the person a handover receipt to acknowledge (default true). */
            'notify' => ['sometimes', 'boolean'],
        ];
    }
}
