<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class AssessRiskRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** 1–5 on the organization's scale, with impact; both null clears the assessment. */
            'likelihood' => ['present', 'nullable', 'integer', 'between:1,5', 'required_with:impact'],
            'impact' => ['present', 'nullable', 'integer', 'between:1,5', 'required_with:likelihood'],
            /** Threats and vulnerabilities from the catalog. */
            'catalog_entry_ids' => ['sometimes', 'array'],
            'catalog_entry_ids.*' => ['uuid'],
            'existing_controls' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
