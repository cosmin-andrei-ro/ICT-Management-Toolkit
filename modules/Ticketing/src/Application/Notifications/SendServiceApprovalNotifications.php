<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Notifications;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Events\ServiceRequestAwaitsApproval;
use Modules\Ticketing\Domain\Events\ServiceRequestDecided;

/**
 * Approvers hear of what waits for them; the requester, of the decision. The
 * requester is never asked to approve their own request.
 */
final class SendServiceApprovalNotifications
{
    public function handle(ServiceRequestAwaitsApproval|ServiceRequestDecided $event): void
    {
        $ticket = $event->ticket;
        $ticket->load(['requester', 'service.approvers']);

        if ($event instanceof ServiceRequestAwaitsApproval) {
            $notification = new TicketUpdate('approval_needed', $ticket->number, $ticket->title, route('ticketing.request-approvals.show', $ticket));
            $recipients = Collection::make($ticket->service->approvers ?? [])->filter(fn (User $user): bool => $user->id !== $ticket->requester_id);
        } else {
            $kind = $event->approved ? 'request_approved' : 'request_rejected';
            $notification = new TicketUpdate($kind, $ticket->number, $ticket->title, route('ticketing.portal.show', $ticket));
            $recipients = Collection::make([$ticket->requester])->filter(fn (?User $user): bool => $user !== null && $user->id !== $event->actor->id);
        }

        $recipients->filter(fn (User $user): bool => $user->canSignIn())->each(fn (User $user) => $user->notify($notification));
    }
}
