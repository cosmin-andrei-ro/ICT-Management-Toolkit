<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Links;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Links\ServiceDeskTickets;
use Modules\Core\Domain\Links\TicketSummary;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Problems\CreateProblem;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * The Ticketing side of Core's ServiceDeskTickets contract. Work handed over by
 * other modules becomes a problem: something to investigate and fix, with no
 * requester waiting on a service level.
 */
final readonly class TicketingServiceDeskTickets implements ServiceDeskTickets
{
    public function __construct(
        private ConnectionInterface $db,
        private CreateProblem $createProblem,
        private ConfigurationItems $items,
    ) {}

    public function isAvailable(): bool
    {
        return true;
    }

    public function raiseProblem(string $title, string $description, string $impact, User $actor, array $itemIds = []): TicketSummary
    {
        $level = Impact::tryFrom($impact) ?? throw new InvalidArgumentException('Unknown impact.');

        $problem = $this->db->transaction(function () use ($title, $description, $level, $actor, $itemIds): Ticket {
            $problem = $this->createProblem->handle(mb_substr($title, 0, 255), $description, $level, Urgency::from($level->value), $actor);

            foreach ($itemIds as $itemId) {
                $this->items->link($problem, $itemId, $actor);
            }

            return $problem;
        });

        return self::summary($problem);
    }

    public function find(array $ticketIds): array
    {
        $found = [];

        foreach (Ticket::query()->whereKey($ticketIds)->get() as $ticket) {
            $found[$ticket->id] = self::summary($ticket);
        }

        return $found;
    }

    private static function summary(Ticket $ticket): TicketSummary
    {
        return new TicketSummary(
            $ticket->id,
            $ticket->number,
            $ticket->title,
            $ticket->status->label(),
            $ticket->status->isOpen(),
            route('ticketing.desk.show', $ticket),
        );
    }
}
