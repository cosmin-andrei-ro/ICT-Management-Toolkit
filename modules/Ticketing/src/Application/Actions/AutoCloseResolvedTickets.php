<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Actions;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Tenancy\OrganizationScope;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Closes tickets left resolved for the grace period (ticketing.tickets.auto_close_after_days),
 * during which the requester could reopen them. Runs across organizations.
 */
final readonly class AutoCloseResolvedTickets
{
    public function __construct(
        private Config $config,
        private OrganizationContext $context,
        private CloseTicket $closeTicket,
    ) {}

    public function handle(): int
    {
        $days = $this->config->get('ticketing.tickets.auto_close_after_days');

        if (! is_int($days) || $days <= 0) {
            return 0;
        }

        $closed = 0;

        Ticket::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('status', TicketStatus::Resolved)
            ->where('resolved_at', '<=', now()->subDays($days))
            ->orderBy('id')
            ->each(function (Ticket $ticket) use (&$closed): void {
                $this->context->runAs($ticket->organization_id, fn () => $this->closeTicket->handle($ticket, null));
                $closed++;
            });

        return $closed;
    }
}
