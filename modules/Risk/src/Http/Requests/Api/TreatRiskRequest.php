<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Risk\Domain\Enums\Treatment;

final class TreatRiskRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'treatment' => ['present', 'nullable', Rule::enum(Treatment::class)],
            'treatment_plan' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'residual_likelihood' => ['present', 'nullable', 'integer', 'between:1,5', 'required_with:residual_impact'],
            'residual_impact' => ['present', 'nullable', 'integer', 'between:1,5', 'required_with:residual_likelihood'],
        ];
    }
}
