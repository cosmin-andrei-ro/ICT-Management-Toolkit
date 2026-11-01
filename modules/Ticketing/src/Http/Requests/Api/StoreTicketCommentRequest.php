<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Ticketing\Domain\Models\Ticket;

final class StoreTicketCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');
        $user = $this->user();

        if (! $ticket instanceof Ticket || $user === null) {
            return false;
        }

        return $user->can($this->boolean('internal') ? 'commentInternally' : 'comment', $ticket);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:20000'],
            'internal' => ['sometimes', 'boolean'],
        ];
    }
}
