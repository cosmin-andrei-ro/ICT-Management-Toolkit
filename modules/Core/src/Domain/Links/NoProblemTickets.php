<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

use InvalidArgumentException;
use Modules\Core\Domain\Models\User;

/**
 * Used when the Ticketing module is disabled.
 */
final class NoProblemTickets implements ProblemTickets
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function raiseProblem(string $title, string $description, string $impact, User $actor, array $itemIds = []): TicketSummary
    {
        throw new InvalidArgumentException('Ticketing is not enabled.');
    }

    public function find(array $ticketIds): array
    {
        return [];
    }
}
