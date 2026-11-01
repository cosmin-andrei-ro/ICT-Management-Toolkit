<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Problems;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Core\Application\Collaboration\AddComment;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Links an incident to the problem behind it (or unlinks it with null). Linking to
 * a known error hands the agent its workaround as an internal note.
 */
final readonly class LinkIncidentToProblem
{
    public function __construct(
        private ConnectionInterface $db,
        private AddComment $addComment,
    ) {}

    public function handle(Ticket $incident, ?Ticket $problem, User $actor): void
    {
        if ($incident->type !== TicketType::Incident) {
            throw new InvalidArgumentException('Only incidents can be linked to a problem.');
        }

        if ($problem !== null && ($problem->type !== TicketType::Problem || $problem->status->isFinal() || $problem->organization_id !== $incident->organization_id)) {
            throw new InvalidArgumentException('Incidents can only be linked to an open problem of the same organization.');
        }

        if ($incident->problem_id === $problem?->id) {
            return;
        }

        $this->db->transaction(function () use ($incident, $problem, $actor): void {
            $incident->problem_id = $problem?->id;
            $incident->save();

            $details = $problem?->problemDetails;

            if ($problem !== null && $details !== null && $details->isKnownError() && ! $incident->status->isFinal()) {
                $this->addComment->handle($incident, $actor, __('ticketing::problems.workaround_note', [
                    'number' => $problem->number,
                    'workaround' => (string) $details->workaround,
                ]), internal: true);
            }
        });
    }
}
