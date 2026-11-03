<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Audit\Domain\Enums\TestResult;

final class ControlTestRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tested_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'result' => ['required', Rule::enum(TestResult::class)],
            'method' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
