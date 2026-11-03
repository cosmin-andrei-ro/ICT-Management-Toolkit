<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\PersonalData;

use Generator;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\PersonalData\PersonalDataEntry;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Tickets a person asked for, works on or watches, with what they wrote in them.
 */
final readonly class TicketingPersonalData
{
    /**
     * @return Generator<PersonalDataEntry>
     */
    public function __invoke(User $person): Generator
    {
        $roles = fn (Ticket $ticket): string => PersonalDataEntry::roles(
            $ticket->requester_id === $person->id ? __('ticketing::personal_data.roles.requester') : null,
            $ticket->assignee_id === $person->id ? __('ticketing::personal_data.roles.assignee') : null,
            $ticket->created_by === $person->id ? __('ticketing::personal_data.roles.creator') : null,
            $ticket->watchers->contains('id', $person->id) ? __('ticketing::personal_data.roles.watcher') : null,
        );

        $tickets = Ticket::query()->with('watchers:id')
            ->where(fn ($query) => $query->where('requester_id', $person->id)->orWhere('assignee_id', $person->id)->orWhere('created_by', $person->id)
                ->orWhereHas('watchers', fn ($query) => $query->whereKey($person->id)))
            ->orderBy('created_at');

        foreach ($tickets->lazy(200) as $ticket) {
            yield new PersonalDataEntry(PersonalDataEntry::text($ticket->type) ?? '', $ticket->number, $ticket->title, $roles($ticket), $ticket->created_at,
                $ticket->requester_id === $person->id ? $ticket->description : null);
        }
    }
}
