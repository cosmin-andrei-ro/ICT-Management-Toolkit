<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Core\Application\Collaboration\AddComment;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Events\ServiceRequestDecided;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * One approval is enough; a rejection closes the request (ADR 0075). The caller has
 * checked who may decide. The row lock keeps two approvers from both deciding.
 */
final readonly class DecideServiceRequest
{
    public function __construct(
        private ConnectionInterface $db,
        private TicketLifecycle $lifecycle,
        private AddComment $comments,
        private AuditTrail $trail,
        private Dispatcher $events,
    ) {}

    /**
     * @throws InvalidArgumentException When the request no longer waits for a decision.
     */
    public function handle(Ticket $ticket, User $actor, bool $approved, string $comment): Ticket
    {
        $this->db->transaction(function () use ($ticket, $actor, $approved, $comment): void {
            $current = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if ($current->status !== TicketStatus::AwaitingApproval) {
                throw new InvalidArgumentException('This request no longer waits for a decision.');
            }

            $ticket->status = TicketStatus::AwaitingApproval;
            $approved ? $this->lifecycle->approve($ticket, $actor) : $this->lifecycle->transition($ticket, TicketStatus::Cancelled, $actor);
            $this->trail->record(AuditEvent::Updated, $ticket, ['approval' => 'waiting'], ['approval' => $approved ? 'approved' : 'rejected'], $actor->id);

            if (trim($comment) !== '') {
                $this->comments->handle($ticket, $actor, $comment, internal: false);
            }
        });

        $this->events->dispatch(new ServiceRequestDecided($ticket, $actor, $approved));

        return $ticket;
    }
}
