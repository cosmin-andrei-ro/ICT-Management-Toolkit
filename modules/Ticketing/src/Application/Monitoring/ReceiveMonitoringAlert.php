<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Monitoring;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Actions\AddTicketComment;
use Modules\Ticketing\Application\Actions\CreateTicket;
use Modules\Ticketing\Application\Actions\NewTicketData;
use Modules\Ticketing\Application\Actions\ResolveTicket;
use Modules\Ticketing\Domain\Enums\ResolutionCode;
use Modules\Ticketing\Domain\Enums\TicketChannel;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Models\MonitoringAlert;

/**
 * Turns monitoring alerts into incidents (ADR 0067):
 * - a firing alert opens an incident, linked to the CMDB item it names;
 * - repeats of the same key are counted while that incident is open;
 * - a cleared alert resolves the incident, unless someone has started work
 *   on it: then it only leaves an internal note, and the agent decides.
 */
final readonly class ReceiveMonitoringAlert
{
    /** Statuses in which nobody has started work: the alert's own outcome decides. */
    private const array UNTOUCHED = [TicketStatus::New, TicketStatus::Assigned];

    public function __construct(
        private ConnectionInterface $db,
        private CreateTicket $createTicket,
        private ResolveTicket $resolveTicket,
        private AddTicketComment $addComment,
        private ConfigurationItems $items,
    ) {}

    /**
     * @return array{action: 'created'|'repeated'|'resolved'|'noted'|'ignored', alert: ?MonitoringAlert}
     */
    public function handle(AlertSignal $signal, User $actor): array
    {
        if (! $signal->firing) {
            return $this->db->transaction(fn (): array => $this->clear($signal, $actor));
        }

        try {
            return $this->db->transaction(fn (): array => $this->fire($signal, $actor));
        } catch (UniqueConstraintViolationException) {
            // The same alert, sent twice at once: the other request opened the incident.
            return $this->db->transaction(fn (): array => $this->fire($signal, $actor));
        }
    }

    /**
     * @return array{action: 'created'|'repeated', alert: MonitoringAlert}
     */
    private function fire(AlertSignal $signal, User $actor): array
    {
        $alert = $this->firingAlert($signal->key);

        if ($alert !== null && $alert->ticket->status->isOpen()) {
            $alert->forceFill(['occurrences' => $alert->occurrences + 1, 'last_seen_at' => now()])->save();

            return ['action' => 'repeated', 'alert' => $alert];
        }

        // Its incident was closed by hand while the alert kept firing: this is a new occurrence.
        $alert?->forceFill(['status' => 'resolved', 'resolved_at' => now()])->save();

        $item = $signal->itemId !== null ? ($this->items->find([$signal->itemId])[$signal->itemId] ?? null) : null;
        $item ??= $signal->item !== null ? $this->items->findByReference($signal->item) : null;
        [$impact, $urgency] = $signal->priority();

        $ticket = $this->createTicket->handle(new NewTicketData(
            type: TicketType::Incident,
            title: mb_substr($signal->title, 0, 200),
            description: $this->description($signal, $item?->name),
            impact: $impact,
            urgency: $urgency,
            channel: TicketChannel::Monitoring,
        ), $actor, $actor);

        if ($item !== null) {
            try {
                $this->items->link($ticket, $item->id, $actor);
            } catch (InvalidArgumentException) {
                $item = null;
            }
        }

        $alert = new MonitoringAlert;
        $alert->forceFill([
            'key' => $signal->key,
            'source' => $signal->source,
            'status' => 'firing',
            'ticket_id' => $ticket->id,
            'configuration_item_id' => $item?->id,
            'occurrences' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ])->save();

        return ['action' => 'created', 'alert' => $alert];
    }

    /**
     * @return array{action: 'resolved'|'noted'|'ignored', alert: ?MonitoringAlert}
     */
    private function clear(AlertSignal $signal, User $actor): array
    {
        $alert = $this->firingAlert($signal->key);

        if ($alert === null) {
            return ['action' => 'ignored', 'alert' => null];
        }

        $alert->forceFill(['status' => 'resolved', 'resolved_at' => now(), 'last_seen_at' => now()])->save();
        $ticket = $alert->ticket;
        $note = __('ticketing::monitoring.cleared', ['count' => $alert->occurrences]);

        if (in_array($ticket->status, self::UNTOUCHED, true)) {
            $this->resolveTicket->handle($ticket, ResolutionCode::NoActionNeeded, $note, $actor);

            return ['action' => 'resolved', 'alert' => $alert];
        }

        if ($ticket->status->isOpen()) {
            $this->addComment->handle($ticket, $actor, $note, internal: true);
        }

        return ['action' => 'noted', 'alert' => $alert];
    }

    private function firingAlert(string $key): ?MonitoringAlert
    {
        return MonitoringAlert::query()->with('ticket')->where('key', $key)->where('status', 'firing')->lockForUpdate()->first();
    }

    private function description(AlertSignal $signal, ?string $itemName): string
    {
        $lines = array_filter([
            $signal->description !== null && trim($signal->description) !== '' ? mb_substr(trim($signal->description), 0, 10000) : null,
            __('ticketing::monitoring.details', [
                'source' => $signal->source ?? __('ticketing::monitoring.unknown_source'),
                'key' => $signal->key,
                'severity' => $signal->severity,
                'item' => $itemName ?? ($signal->item ?? __('ticketing::monitoring.no_item')),
            ]),
        ]);

        return implode("\n\n", $lines);
    }
}
