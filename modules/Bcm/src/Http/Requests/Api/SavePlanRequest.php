<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Http\Requests\Api;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Bcm\Domain\Enums\PlanType;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Core\Http\Api\ActiveMember;

/**
 * Creating (POST) or changing (PATCH: only the fields sent) a plan. Its content
 * is written in versions, submitted and approved on screen.
 */
final class SavePlanRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $plan = $this->route('plan');
        $planId = $plan instanceof Plan ? $plan->id : null;

        return [
            'type' => [$required, Rule::enum(PlanType::class)],
            'name' => [$required, 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) use ($planId): void {
                if (is_string($value) && Plan::query()->whereRaw('lower(name) = ?', [mb_strtolower(trim($value))])->when($planId !== null, fn ($query) => $query->whereKeyNot($planId))->exists()) {
                    $fail(__('bcm::plans.errors.name_taken'));
                }
            }],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'owner_id' => [$required, 'uuid', new ActiveMember],
            'deputy_id' => ['sometimes', 'nullable', 'uuid', new ActiveMember, 'different:owner_id'],
            'status' => ['sometimes', Rule::in(['active', 'retired'])],
        ];
    }
}
