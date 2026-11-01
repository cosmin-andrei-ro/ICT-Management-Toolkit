<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Notifications;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Events\SlaBreached;
use Modules\Ticketing\Domain\Events\SlaWarning;

/**
 * Tells the assignee and the queue's team lead; if nobody owns the ticket,
 * the whole queue team, so an unassigned ticket does not breach silently.
 */
final class SendSlaNotifications
{
    public function handle(SlaWarning|SlaBreached $event): void
    {
        $ticket = $event->timer->ticket()->with(['assignee', 'queue.team.lead', 'queue.team.members'])->firstOrFail();
        $kind = 'sla_'.$event->timer->metric->value.'_'.($event instanceof SlaWarning ? 'warning' : 'breached');

        $team = $ticket->queue?->team;
        $recipients = $ticket->assignee !== null
            ? [$ticket->assignee, $team?->lead]
            : [...($team !== null ? $team->members->all() : []), $team?->lead];

        $notification = new TicketUpdate($kind, $ticket->number, $ticket->title, route('ticketing.desk.show', $ticket));

        Collection::make($recipients)
            ->filter(fn (?User $user): bool => $user !== null && $user->canSignIn())
            ->unique('id')
            ->each(fn (User $user) => $user->notify($notification));
    }
}
