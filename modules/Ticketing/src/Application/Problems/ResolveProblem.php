<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Problems;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Actions\ResolveTicket;
use Modules\Ticketing\Domain\Enums\ResolutionCode;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Models\Ticket;

final readonly class ResolveProblem
{
    public function __construct(
        private ConnectionInterface $db,
        private ResolveTicket $resolveTicket,
    ) {}

    /**
     * @param  bool  $resolveIncidents  Also resolve linked open incidents. Their requesters are
     *                                  notified as usual and can reopen if it is not fixed for them.
     * @return int Incidents resolved with the problem.
     */
    public function handle(Ticket $problem, ResolutionCode $code, string $note, bool $resolveIncidents, User $actor): int
    {
        if ($problem->type !== TicketType::Problem) {
            throw new InvalidArgumentException('Not a problem.');
        }

        return $this->db->transaction(function () use ($problem, $code, $note, $resolveIncidents, $actor): int {
            $this->resolveTicket->handle($problem, $code, $note, $actor);

            if (! $resolveIncidents) {
                return 0;
            }

            $incidents = $problem->incidents()->whereIn('status', TicketStatus::open())->get();

            foreach ($incidents as $incident) {
                $this->resolveTicket->handle($incident, $code, __('ticketing::problems.resolved_by_problem', [
                    'number' => $problem->number,
                    'note' => trim($note),
                ]), $actor);
            }

            return $incidents->count();
        });
    }
}
