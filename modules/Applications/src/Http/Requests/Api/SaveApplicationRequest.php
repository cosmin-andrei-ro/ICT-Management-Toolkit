<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Http\Requests\Api;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Applications\Domain\Models\Application;
use Modules\Core\Domain\Models\Department;
use Modules\Core\Http\Api\ActiveMember;

/**
 * Registering (POST) or changing (PATCH: only the fields sent) an application.
 * Its documentation sheet is written, submitted and approved on screen.
 */
final class SaveApplicationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $application = $this->route('application');
        $applicationId = $application instanceof Application ? $application->id : null;
        $unique = fn (string $function, string $message): Closure => function (string $attribute, mixed $value, Closure $fail) use ($function, $message, $applicationId): void {
            $normalized = is_string($value) ? ($function === 'upper' ? strtoupper(trim($value)) : mb_strtolower(trim($value))) : '';

            if ($normalized !== '' && Application::query()->whereRaw($function === 'upper' ? 'upper(code) = ?' : 'lower(name) = ?', [$normalized])->when($applicationId !== null, fn ($query) => $query->whereKeyNot($applicationId))->exists()) {
                $fail(__($message));
            }
        };

        return [
            'code' => [$required, 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $unique('upper', 'applications::applications.errors.code_taken')],
            'name' => [$required, 'string', 'max:255', $unique('lower', 'applications::applications.errors.name_taken')],
            // Scoped: departments of other organizations are not found.
            'department_id' => ['sometimes', 'nullable', 'uuid', Rule::in(Department::query()->pluck('id')->all())],
            'department_email' => ['sometimes', 'nullable', 'string', 'max:255', 'email'],
            'business_owner_id' => [$required, 'uuid', new ActiveMember],
            'technical_owner_id' => [$required, 'uuid', new ActiveMember],
            'deputy_id' => ['sometimes', 'nullable', 'uuid', new ActiveMember],
        ];
    }
}
