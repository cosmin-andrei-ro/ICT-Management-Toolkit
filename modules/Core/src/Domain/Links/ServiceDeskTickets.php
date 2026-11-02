<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

use InvalidArgumentException;
use Modules\Core\Domain\Models\User;

/**
 * Work other modules hand to the service desk (a finding of a recovery test).
 * Implemented by the Ticketing module; when it is disabled, nothing is raised.
 */
interface ServiceDeskTickets
{
    public function isAvailable(): bool;

    /**
     * Opens a problem for the service desk to investigate and fix.
     *
     * @param  string  $impact  low, medium or high
     * @param  list<string>  $itemIds  Configuration items concerned.
     *
     * @throws InvalidArgumentException for an unknown impact, or when the service desk is not available
     */
    public function raiseProblem(string $title, string $description, string $impact, User $actor, array $itemIds = []): TicketSummary;

    /**
     * @param  list<string>  $ticketIds
     * @return array<string, TicketSummary> By ticket id; unknown ids are left out.
     */
    public function find(array $ticketIds): array;
}
