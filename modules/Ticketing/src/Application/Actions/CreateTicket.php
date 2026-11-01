<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Events\TicketCreated;
use Modules\Ticketing\Domain\Models\Category;
use Modules\Ticketing\Domain\Models\Queue;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Domain\Workflow\PriorityMatrix;
use Modules\Ticketing\Infrastructure\TicketNumberGenerator;

final readonly class CreateTicket
{
    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
        private TicketNumberGenerator $numbers,
        private PriorityMatrix $priorities,
        private TicketLifecycle $lifecycle,
        private Dispatcher $events,
    ) {}

    /**
     * @param  User  $requester  Who needs help; may differ from $actor when an agent logs a call.
     */
    public function handle(NewTicketData $data, User $requester, User $actor): Ticket
    {
        $organizationId = $this->context->requireId();

        if (! $requester->isMemberOf($organizationId)) {
            throw new InvalidArgumentException('The requester must belong to the current organization.');
        }

        // Scoped lookups: a category or queue of another organization is simply not found.
        $category = $data->categoryId !== null
            ? Category::query()->where('is_active', true)->findOrFail($data->categoryId)
            : null;

        $queue = $data->queueId !== null
            ? Queue::query()->where('is_active', true)->findOrFail($data->queueId)
            : Queue::query()->where('is_active', true)->where('is_default', true)->first();

        $ticket = $this->db->transaction(function () use ($data, $requester, $actor, $organizationId, $category, $queue): Ticket {
            $ticket = new Ticket;
            $ticket->forceFill([
                'organization_id' => $organizationId,
                'number' => $this->numbers->next($organizationId, $data->type),
                'type' => $data->type,
                'title' => trim($data->title),
                'description' => trim($data->description),
                'status' => TicketStatus::New,
                'impact' => $data->impact,
                'urgency' => $data->urgency,
                'priority' => $this->priorities->resolve($organizationId, $data->impact, $data->urgency),
                'channel' => $data->channel,
                'requester_id' => $requester->id,
                'category_id' => $category?->id,
                'queue_id' => $queue?->id,
            ])->save();

            $this->lifecycle->record($ticket, null, TicketStatus::New, $actor);

            return $ticket;
        });

        $this->events->dispatch(new TicketCreated($ticket, $actor));

        return $ticket;
    }
}
