<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Cmdb\Domain\Enums\RelationshipType;

final class StoreRelationshipRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_id' => ['required', 'uuid'],
            'type' => ['required', Rule::enum(RelationshipType::class)],
            'target_id' => ['required', 'uuid', 'different:source_id'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
