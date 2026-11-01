<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Workflow;

use DomainException;
use Modules\Ticketing\Domain\Enums\TicketStatus;

final class InvalidTransition extends DomainException
{
    public function __construct(public readonly TicketStatus $from, public readonly TicketStatus $to)
    {
        parent::__construct("A ticket cannot go from [{$from->value}] to [{$to->value}].");
    }
}
