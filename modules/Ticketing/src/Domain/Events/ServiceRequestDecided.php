<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Events;

use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * An approver approved or rejected a catalog request.
 */
final readonly class ServiceRequestDecided
{
    public function __construct(
        public Ticket $ticket,
        public User $actor,
        public bool $approved,
    ) {}
}
