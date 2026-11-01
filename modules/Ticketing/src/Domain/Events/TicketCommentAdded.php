<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\Core\Domain\Models\Comment;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * A comment was added; listeners must check is_internal before notifying requesters. Dispatched after the transaction commits.
 */
final readonly class TicketCommentAdded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public Ticket $ticket,
        public ?User $actor,
        public Comment $comment,
    ) {}
}
