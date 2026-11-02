<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Links;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Tickets of the service desk (e.g. an approved change), as other modules link to them (LinkedRecords): only those the viewer may see.
 */
final class TicketRecords
{
    /**
     * @param  list<string>  $ids
     * @return list<LinkedRecord>
     */
    public static function resolve(array $ids, User $viewer): array
    {
        return self::records(Ticket::query()->whereKey($ids)->latest()->limit(50)->get(), $viewer);
    }

    /**
     * @return list<LinkedRecord>
     */
    public static function search(string $term, User $viewer): array
    {
        // Searching every ticket is for the people who see every ticket.
        if (! $viewer->can('ticketing.ticket.view_all')) {
            return [];
        }

        $like = LinkedRecords::like($term);

        return self::records(Ticket::query()
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->whereLike('number', $like)->orWhereLike('title', $like)))
            ->latest()
            ->limit(50)
            ->get(), $viewer);
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @return list<LinkedRecord>
     */
    private static function records(Collection $tickets, User $viewer): array
    {
        $gate = Gate::forUser($viewer);

        return array_values($tickets
            ->filter(fn (Ticket $ticket): bool => $gate->allows('view', $ticket))
            ->map(fn (Ticket $ticket): LinkedRecord => new LinkedRecord(
                $ticket->id,
                $ticket->number.' '.$ticket->title,
                route($gate->allows('work', $ticket) ? 'ticketing.desk.show' : 'ticketing.portal.show', $ticket),
                $ticket->status->label(),
            ))
            ->all());
    }
}
