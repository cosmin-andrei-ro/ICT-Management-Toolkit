<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Risk\Domain\Enums\RiskCategory;
use Modules\Risk\Domain\Enums\RiskStatus;

/**
 * Registering (POST) or changing (PATCH: only the fields sent) a risk. Its
 * assessment and treatment have their own endpoints, open to the risk's owner.
 */
final class SaveRiskRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'category' => [$required, Rule::enum(RiskCategory::class)],
            'owner_id' => [$required, 'uuid'],
            'status' => ['sometimes', Rule::enum(RiskStatus::class)],
        ];
    }
}
