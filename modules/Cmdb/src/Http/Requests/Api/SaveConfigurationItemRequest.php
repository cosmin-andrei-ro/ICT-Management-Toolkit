<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Http\Requests\Api;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Cmdb\Domain\Enums\CiStatus;
use Modules\Cmdb\Domain\Enums\Criticality;
use Modules\Cmdb\Domain\Enums\Environment;
use Modules\Cmdb\Domain\Models\ConfigurationItem;

/**
 * Creating (POST) or changing (PATCH: only the fields sent) a configuration
 * item. Authorization is the controller's, through the policy.
 */
final class SaveConfigurationItemRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'sometimes';
        $item = $this->route('item');
        $itemId = $item instanceof ConfigurationItem ? $item->id : null;

        return [
            'type_id' => [$required, 'uuid'],
            'name' => [$required, 'string', 'max:255'],
            'identifier' => ['sometimes', 'nullable', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) use ($itemId): void {
                // Case-insensitive, like the database's unique index.
                if (is_string($value) && trim($value) !== '' && ConfigurationItem::query()->whereRaw('lower(identifier) = ?', [mb_strtolower(trim($value))])->when($itemId !== null, fn ($query) => $query->whereKeyNot($itemId))->exists()) {
                    $fail(__('cmdb::items.ui.identifier_taken'));
                }
            }],
            'status' => ['sometimes', Rule::enum(CiStatus::class)],
            'environment' => ['sometimes', 'nullable', Rule::enum(Environment::class)],
            'criticality' => ['sometimes', Rule::enum(Criticality::class)],
            'owner_id' => ['sometimes', 'nullable', 'uuid'],
            'team_id' => ['sometimes', 'nullable', 'uuid'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'recovery_time_minutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:5256000'],
            'recovery_point_minutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:5256000'],
            /** Attribute values of the type, keyed by attribute key; checked against the type's schema. */
            'properties' => ['sometimes', 'array'],
        ];
    }
}
