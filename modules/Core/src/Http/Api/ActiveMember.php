<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Api;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * An active member of the current organization, as the screens' person pickers
 * offer: owners, deputies and assignees given through the API (ADR 0064).
 */
final readonly class ActiveMember implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $organizationId = app(OrganizationContext::class)->id();

        if (! is_string($value) || $organizationId === null || ! User::query()->whereKey($value)->where('is_active', true)
            ->whereHas('organizations', fn ($query) => $query->whereKey($organizationId))->exists()) {
            $fail(__('core::api.active_member'));
        }
    }
}
