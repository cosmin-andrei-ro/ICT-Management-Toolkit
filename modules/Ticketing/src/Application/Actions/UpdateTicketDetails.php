<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Actions;

use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Models\Category;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Domain\Workflow\PriorityMatrix;

/**
 * Triage by the service desk: wording, category, impact and urgency. Priority is
 * always recomputed, never set by hand.
 */
final readonly class UpdateTicketDetails
{
    public function __construct(
        private TicketLifecycle $lifecycle,
        private PriorityMatrix $priorities,
    ) {}

    public function handle(Ticket $ticket, string $title, string $description, Impact $impact, Urgency $urgency, ?string $categoryId): Ticket
    {
        $this->lifecycle->assertNotFinal($ticket);

        $category = $categoryId !== null ? Category::query()->where('is_active', true)->findOrFail($categoryId) : null;

        $ticket->title = trim($title);
        $ticket->description = trim($description);
        $ticket->impact = $impact;
        $ticket->urgency = $urgency;
        $ticket->priority = $this->priorities->resolve($ticket->organization_id, $impact, $urgency);
        $ticket->category_id = $category?->id;
        $ticket->save();

        return $ticket;
    }
}
