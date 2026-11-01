<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Livewire\Concerns;

use InvalidArgumentException;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Links\ConfigurationItemSummary;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Affected configuration items of a ticket, through Core's contract: works the
 * same whether the CMDB module is enabled or not.
 *
 * @property-read Ticket $ticket
 */
trait LinksConfigurationItems
{
    public string $itemSearch = '';

    public function linkItem(string $itemId, ConfigurationItems $items): void
    {
        $this->authorize('work', $this->ticket);

        try {
            $items->link($this->ticket, $itemId, $this->currentUser());
        } catch (InvalidArgumentException) {
            $this->addError('itemSearch', __('ticketing::ui.items.invalid'));

            return;
        }

        $this->reset('itemSearch');
    }

    public function unlinkItem(string $itemId, ConfigurationItems $items): void
    {
        $this->authorize('work', $this->ticket);
        $items->unlink($this->ticket, $itemId, $this->currentUser());
    }

    /**
     * @return array{available: bool, linked: list<ConfigurationItemSummary>, candidates: list<ConfigurationItemSummary>, impact: list<ConfigurationItemSummary>}
     */
    protected function configurationItemData(ConfigurationItems $items, Ticket $ticket): array
    {
        $linked = $items->linkedTo($ticket);
        $linkedIds = array_map(fn (ConfigurationItemSummary $item): string => $item->id, $linked);

        return [
            'available' => $items->isAvailable(),
            'linked' => $linked,
            'candidates' => array_values(array_filter($items->search($this->itemSearch), fn (ConfigurationItemSummary $item): bool => ! in_array($item->id, $linkedIds, true))),
            'impact' => $linked !== [] ? $items->impactOf($ticket) : [],
        ];
    }

    private function currentUser(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
