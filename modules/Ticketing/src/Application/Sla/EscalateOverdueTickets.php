<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Sla;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Tenancy\OrganizationScope;
use Modules\Ticketing\Domain\Events\TicketEscalated;
use Modules\Ticketing\Domain\Models\OlaTimer;
use Modules\Ticketing\Domain\Models\Queue;
use Modules\Ticketing\Domain\Models\SlaTimer;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Tells the queue's escalation person about a target missed longer ago than the
 * queue's delay and still not met (ADR 0074). Once per clock. Nothing moves: the
 * ticket keeps its queue and its assignee.
 */
final readonly class EscalateOverdueTickets
{
    public function __construct(
        private OrganizationContext $context,
        private Dispatcher $events,
    ) {}

    public function handle(): int
    {
        $escalated = 0;

        foreach ([SlaTimer::class, OlaTimer::class] as $class) {
            $class::query()
                ->withoutGlobalScope(OrganizationScope::class)
                ->whereNotNull('running_since')
                ->whereNotNull('breached_at')
                ->whereNull('escalated_at')
                ->whereIn('ticket_id', $this->ticketsInQueuesWithEscalation())
                ->orderBy('id')
                ->each(function (SlaTimer|OlaTimer $timer) use (&$escalated): void {
                    $escalated += $this->escalate($timer) ? 1 : 0;
                });
        }

        return $escalated;
    }

    private function escalate(SlaTimer|OlaTimer $timer): bool
    {
        return $this->context->runAs($timer->organization_id, function () use ($timer): bool {
            $ticket = Ticket::query()->with('queue')->find($timer->ticket_id);
            $queue = $ticket?->queue;

            if ($ticket === null || $queue === null || $timer->breached_at === null
                || $timer->breached_at->copy()->addMinutes($queue->escalate_after_minutes)->gt(CarbonImmutable::now())) {
                return false;
            }

            $timer->escalated_at = CarbonImmutable::now();
            $timer->save();
            $this->events->dispatch(new TicketEscalated($ticket));

            return true;
        });
    }

    private function ticketsInQueuesWithEscalation(): Closure
    {
        return fn (QueryBuilder $tickets) => $tickets->select('ticketing_tickets.id')->from('ticketing_tickets')
            ->join((new Queue)->getTable().' as escalating_queues', 'escalating_queues.id', '=', 'ticketing_tickets.queue_id')
            ->whereNotNull('escalating_queues.escalation_user_id');
    }
}
