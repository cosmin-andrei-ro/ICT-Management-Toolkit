<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Notifications;

use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Events\OlaBreached;
use Modules\Ticketing\Domain\Events\TicketEscalated;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * A missed OLA goes to the lead of the team that has the ticket; an escalation to
 * the person named on the queue. Links only: no ticket content in the mail.
 */
final class SendOlaNotifications
{
    public function handle(OlaBreached|TicketEscalated $event): void
    {
        if ($event instanceof OlaBreached) {
            $ticket = $event->timer->ticket()->first();
            $recipient = $event->timer->team()->with('lead')->first()?->lead;
            $kind = 'ola_breached';
        } else {
            $ticket = $event->ticket;
            $recipient = $ticket->queue()->with('escalationUser')->first()?->escalationUser;
            $kind = 'escalated';
        }

        if ($ticket instanceof Ticket && $recipient instanceof User && $recipient->canSignIn()) {
            $recipient->notify(new TicketUpdate($kind, $ticket->number, $ticket->title, route('ticketing.desk.show', $ticket)));
        }
    }
}
