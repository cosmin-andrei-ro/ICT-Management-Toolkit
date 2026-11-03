<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Risk\Domain\Enums\ActionStatus;

/**
 * Adding a treatment action (POST) or moving it on (PATCH: status only).
 */
final class RiskActionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->isMethod('POST')
            ? [
                'title' => ['required', 'string', 'max:255'],
                'owner_id' => ['nullable', 'uuid'],
                'due_on' => ['nullable', 'date_format:Y-m-d'],
            ]
            : ['status' => ['required', Rule::enum(ActionStatus::class)]];
    }
}
