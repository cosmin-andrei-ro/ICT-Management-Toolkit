<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Problems;

use Illuminate\Database\ConnectionInterface;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Actions\CreateTicket;
use Modules\Ticketing\Application\Actions\NewTicketData;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\TicketChannel;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Models\ProblemDetails;
use Modules\Ticketing\Domain\Models\Ticket;

final readonly class CreateProblem
{
    public function __construct(
        private ConnectionInterface $db,
        private CreateTicket $createTicket,
        private LinkIncidentToProblem $link,
    ) {}

    /**
     * @param  list<Ticket>  $incidents  Incidents already known to share this cause.
     */
    public function handle(string $title, string $description, Impact $impact, Urgency $urgency, User $actor, array $incidents = []): Ticket
    {
        return $this->db->transaction(function () use ($title, $description, $impact, $urgency, $actor, $incidents): Ticket {
            // The problem manager owns the problem record; it has no external requester.
            $problem = $this->createTicket->handle(
                new NewTicketData(TicketType::Problem, $title, $description, $impact, $urgency, TicketChannel::Agent),
                $actor,
                $actor,
            );

            (new ProblemDetails)->forceFill([
                'ticket_id' => $problem->id,
                'organization_id' => $problem->organization_id,
            ])->save();

            foreach ($incidents as $incident) {
                $this->link->handle($incident, $problem, $actor);
            }

            return $problem;
        });
    }
}
