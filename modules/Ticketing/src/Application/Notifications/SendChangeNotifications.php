<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Notifications;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Changes\CabApprovers;
use Modules\Ticketing\Domain\Events\ChangeApproved;
use Modules\Ticketing\Domain\Events\ChangeRejected;
use Modules\Ticketing\Domain\Events\ChangeSubmitted;

/**
 * Board members learn of changes waiting for them; the requester learns the outcome.
 */
final readonly class SendChangeNotifications
{
    public function __construct(private CabApprovers $approvers) {}

    public function handle(ChangeSubmitted|ChangeApproved|ChangeRejected $event): void
    {
        $change = $event->ticket->load(['requester', 'changeDetails.cabBoard']);
        $board = $change->changeDetails?->cabBoard;

        [$kind, $recipients, $url] = match (true) {
            $event instanceof ChangeSubmitted => ['change_submitted', $board !== null ? $this->approvers->for($change, $board)->all() : [], route('ticketing.changes.review', $change)],
            $event instanceof ChangeApproved => ['change_approved', [$change->requester], route('ticketing.desk.show', $change)],
            $event instanceof ChangeRejected => ['change_rejected', [$change->requester], route('ticketing.desk.show', $change)],
        };

        $notification = new TicketUpdate($kind, $change->number, $change->title, $url);

        Collection::make($recipients)
            ->filter(fn (?User $user): bool => $user !== null && $user->canSignIn() && $user->id !== $event->actor?->id)
            ->unique('id')
            ->each(fn (User $user) => $user->notify($notification));
    }
}
