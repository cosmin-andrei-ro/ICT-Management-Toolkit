<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * @mixin Ticket
 */
final class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'type' => $this->type->value,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'impact' => $this->impact->value,
            'urgency' => $this->urgency->value,
            'priority' => $this->priority->value,
            'channel' => $this->channel->value,
            'requester' => $this->whenLoaded('requester', fn (): array => ['id' => $this->requester->id, 'name' => $this->requester->name]),
            'assignee' => $this->whenLoaded('assignee', fn (): ?array => $this->assignee !== null ? ['id' => $this->assignee->id, 'name' => $this->assignee->name] : null),
            'queue' => $this->whenLoaded('queue', fn (): ?array => $this->queue !== null ? ['id' => $this->queue->id, 'name' => $this->queue->name] : null),
            'category' => $this->whenLoaded('category', fn (): ?array => $this->category !== null ? ['id' => $this->category->id, 'name' => $this->category->name] : null),
            'service' => $this->whenLoaded('service', fn (): ?array => $this->service !== null ? ['id' => $this->service->id, 'name' => $this->service->name] : null),
            'service_answers' => $this->serviceAnswers(),
            'resolution_code' => $this->resolution_code?->value,
            'first_responded_at' => $this->first_responded_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
        ];
    }
}
