<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Audit\Domain\Enums\ControlStatus;

/**
 * Creating (POST) or changing (PATCH: only the fields sent) a control.
 */
final class SaveControlRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'owner_id' => ['sometimes', 'nullable', 'uuid'],
            'status' => ['sometimes', Rule::enum(ControlStatus::class)],
            'implementation_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'test_interval_months' => ['sometimes', 'nullable', 'integer', 'between:1,60'],
        ];
    }
}
