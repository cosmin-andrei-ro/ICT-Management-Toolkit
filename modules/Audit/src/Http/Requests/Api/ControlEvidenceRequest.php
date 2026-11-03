<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class ControlEvidenceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** A record of the toolkit, e.g. "risk.risk", "bcm.plan", "ticketing.ticket", "documentation.page". */
            'type' => ['required', 'string', 'max:64'],
            'id' => ['required', 'uuid'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
