<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Models\Ticket;

final class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Ticket::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(TicketType::class)->only(TicketType::requestable())],
            'title' => ['required', 'string', 'min:5', 'max:200'],
            'description' => ['required', 'string', 'max:20000'],
            'impact' => ['sometimes', Rule::enum(Impact::class)],
            'urgency' => ['sometimes', Rule::enum(Urgency::class)],
            'category_id' => ['nullable', 'uuid', Rule::exists('ticketing_categories', 'id')
                ->where('organization_id', app(OrganizationContext::class)->id())
                ->where('is_active', true)],
        ];
    }
}
