<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Risks;

use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Risks\RiskSignal;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Operational risk IT support sees: the same CMDB item failing again
 * and again (three incidents or more in 90 days).
 */
final readonly class IncidentRiskSignals
{
    public const int MIN_INCIDENTS = 3;

    public const int DAYS = 90;

    public function __construct(private ConfigurationItems $items) {}

    /**
     * @return list<RiskSignal>
     */
    public function __invoke(): array
    {
        if (! $this->items->isAvailable()) {
            return [];
        }

        $incidentIds = array_values(array_filter(
            Ticket::query()->where('type', TicketType::Incident->value)->where('created_at', '>=', now()->subDays(self::DAYS))->limit(10000)->pluck('id')->all(),
            is_string(...),
        ));
        $counts = [];

        foreach ($this->items->linkedIds((new Ticket)->getMorphClass(), $incidentIds) as $itemIds) {
            foreach (array_unique($itemIds) as $itemId) {
                $counts[$itemId] = ($counts[$itemId] ?? 0) + 1;
            }
        }

        $recurring = array_keys(array_filter($counts, fn (int $count): bool => $count >= self::MIN_INCIDENTS));
        $signals = [];

        foreach ($this->items->recoveryProfiles($recurring) as $item) {
            $replace = ['item' => $item->name, 'count' => $counts[$item->id], 'days' => self::DAYS];
            $signals[] = new RiskSignal(
                'ticketing.recurring:'.$item->id, (string) __('ticketing::risk_signals.source'),
                (string) __('ticketing::risk_signals.recurring.title', $replace),
                (string) __('ticketing::risk_signals.recurring.description', $replace),
                'operational', [['type' => 'configuration_item', 'id' => $item->id]],
                ['Software failure', 'Hardware failure'], [],
                4, null, $item->url,
            );
        }

        return $signals;
    }
}
