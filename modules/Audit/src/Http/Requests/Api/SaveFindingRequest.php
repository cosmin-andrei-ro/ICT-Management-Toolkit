<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Audit\Domain\Enums\FindingSeverity;

/**
 * Raising (POST) or changing (PATCH: only the fields sent) a finding. Submitting
 * it for verification and verifying it stay on screen.
 */
final class SaveFindingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:255'],
            'description' => [$required, 'string', 'max:10000'],
            'severity' => [$required, Rule::enum(FindingSeverity::class)],
            'owner_id' => ['sometimes', 'nullable', 'uuid'],
            'due_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'control_id' => ['sometimes', 'nullable', 'uuid'],
            'requirement_id' => ['sometimes', 'nullable', 'uuid'],
            /** The audit it was found in; set at creation only. */
            'audit_id' => $creating ? ['sometimes', 'nullable', 'uuid'] : ['prohibited'],
            'root_cause' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
