<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Webhooks;

use Modules\Core\Application\Webhooks\PublishWebhookEvent;
use Modules\Core\Domain\Webhooks\WebhookEvents;
use Modules\Ticketing\Domain\Events\ChangeApproved;
use Modules\Ticketing\Domain\Events\ChangeRejected;
use Modules\Ticketing\Domain\Events\ChangeSubmitted;
use Modules\Ticketing\Domain\Events\SlaBreached;
use Modules\Ticketing\Domain\Events\SlaWarning;
use Modules\Ticketing\Domain\Events\TicketAssigned;
use Modules\Ticketing\Domain\Events\TicketCreated;
use Modules\Ticketing\Domain\Events\TicketResolved;
use Modules\Ticketing\Domain\Events\TicketStatusChanged;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Ticket and change events for outbound webhooks (ADR 0066): the number, the
 * title, what changed in a word, and the API link - never the description,
 * the people or the comments.
 */
final readonly class PublishTicketWebhooks
{
    public const array EVENTS = [
        'ticket.created' => 'ticketing::tickets.webhooks.created',
        'ticket.assigned' => 'ticketing::tickets.webhooks.assigned',
        'ticket.status_changed' => 'ticketing::tickets.webhooks.status_changed',
        'ticket.resolved' => 'ticketing::tickets.webhooks.resolved',
        'ticket.sla_warning' => 'ticketing::tickets.webhooks.sla_warning',
        'ticket.sla_breached' => 'ticketing::tickets.webhooks.sla_breached',
        'change.submitted' => 'ticketing::tickets.webhooks.change_submitted',
        'change.approved' => 'ticketing::tickets.webhooks.change_approved',
        'change.rejected' => 'ticketing::tickets.webhooks.change_rejected',
    ];

    public function __construct(private PublishWebhookEvent $publish) {}

    public static function register(WebhookEvents $events): void
    {
        foreach (self::EVENTS as $type => $label) {
            $events->register($type, $label);
        }
    }

    public function handle(TicketCreated|TicketAssigned|TicketStatusChanged|TicketResolved|SlaWarning|SlaBreached|ChangeSubmitted|ChangeApproved|ChangeRejected $event): void
    {
        [$type, $ticket, $changes] = match (true) {
            $event instanceof TicketCreated => ['ticket.created', $event->ticket, null],
            $event instanceof TicketAssigned => ['ticket.assigned', $event->ticket, null],
            $event instanceof TicketStatusChanged => ['ticket.status_changed', $event->ticket, ['status' => ['from' => $event->from->value, 'to' => $event->to->value]]],
            $event instanceof TicketResolved => ['ticket.resolved', $event->ticket, null],
            $event instanceof SlaWarning => ['ticket.sla_warning', $event->timer->ticket, ['metric' => $event->timer->metric->value]],
            $event instanceof SlaBreached => ['ticket.sla_breached', $event->timer->ticket, ['metric' => $event->timer->metric->value]],
            $event instanceof ChangeSubmitted => ['change.submitted', $event->ticket, null],
            $event instanceof ChangeApproved => ['change.approved', $event->ticket, null],
            $event instanceof ChangeRejected => ['change.rejected', $event->ticket, null],
        };

        $this->publish->handle($type, [
            'id' => $ticket->id,
            'reference' => $ticket->number,
            'title' => $ticket->title,
            'url' => route('api.v1.tickets.show', $ticket),
            ...($changes !== null ? ['changes' => $changes] : []),
        ], $ticket->organization_id);
    }
}
