<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Http\Requests\Api;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Core\Domain\Models\Department;
use Modules\Core\Http\Api\ActiveMember;

/**
 * Creating (POST) or changing (PATCH: only the fields sent) a business process.
 * Its impact analysis is filled in, submitted and approved on screen.
 */
final class SaveProcessRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $process = $this->route('process');
        $processId = $process instanceof BusinessProcess ? $process->id : null;

        return [
            'name' => [$required, 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) use ($processId): void {
                // Case-insensitive, like the database's unique index.
                if (is_string($value) && BusinessProcess::query()->whereRaw('lower(name) = ?', [mb_strtolower(trim($value))])->when($processId !== null, fn ($query) => $query->whereKeyNot($processId))->exists()) {
                    $fail(__('bia::api.name_taken'));
                }
            }],
            // Scoped: departments of other organizations are not found.
            'department_id' => ['sometimes', 'nullable', 'uuid', Rule::in(Department::query()->pluck('id')->all())],
            'owner_id' => [$required, 'uuid', new ActiveMember],
            'deputy_id' => ['sometimes', 'nullable', 'uuid', new ActiveMember, 'different:owner_id'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'peak_periods' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::in(['active', 'retired'])],
        ];
    }
}
