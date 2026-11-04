<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Events;

use Modules\Ticketing\Domain\Models\Ticket;

/**
 * A catalog request needs a decision before IT starts on it.
 */
final readonly class ServiceRequestAwaitsApproval
{
    public function __construct(public Ticket $ticket) {}
}
