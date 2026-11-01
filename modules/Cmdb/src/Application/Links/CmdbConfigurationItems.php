<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Application\Links;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Modules\Cmdb\Application\Impact\ImpactAnalysis;
use Modules\Cmdb\Application\Impact\ImpactedItem;
use Modules\Cmdb\Domain\Models\CiLink;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Links\ConfigurationItemSummary;
use Modules\Core\Domain\Models\User;

/**
 * The CMDB side of Core's ConfigurationItems contract. Links are audited on the
 * linked record (the ticket), where people look for its history.
 */
final readonly class CmdbConfigurationItems implements ConfigurationItems
{
    public function __construct(
        private ImpactAnalysis $impact,
        private AuditTrail $trail,
    ) {}

    public function isAvailable(): bool
    {
        return true;
    }

    public function search(string $terms, int $limit = 10): array
    {
        if (mb_strlen(trim($terms)) < 2) {
            return [];
        }

        return array_values(ConfigurationItem::query()
            ->with('type')
            ->search(mb_substr($terms, 0, 200))
            ->where('status', '!=', 'retired')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (ConfigurationItem $item): ConfigurationItemSummary => self::summary($item))
            ->all());
    }

    public function linkedTo(Model $record): array
    {
        $ids = CiLink::query()->where('linkable_type', $record->getMorphClass())->where('linkable_id', $record->getKey())->pluck('configuration_item_id');

        return array_values(ConfigurationItem::query()
            ->with('type')
            ->whereKey($ids->all())
            ->orderBy('name')
            ->get()
            ->map(fn (ConfigurationItem $item): ConfigurationItemSummary => self::summary($item))
            ->all());
    }

    public function link(Model $record, string $itemId, User $actor): void
    {
        // Scoped lookup: an item of another organization is not found.
        $item = ConfigurationItem::query()->find($itemId) ?? throw new InvalidArgumentException('Unknown configuration item.');

        if ($record->getAttribute('organization_id') !== $item->organization_id) {
            throw new InvalidArgumentException('The record and the item belong to different organizations.');
        }

        $exists = CiLink::query()
            ->where('configuration_item_id', $item->id)
            ->where('linkable_type', $record->getMorphClass())
            ->where('linkable_id', $record->getKey())
            ->exists();

        if ($exists) {
            return;
        }

        $link = new CiLink;
        $link->forceFill([
            'organization_id' => $item->organization_id,
            'configuration_item_id' => $item->id,
            'linkable_type' => $record->getMorphClass(),
            'linkable_id' => $record->getKey(),
            'created_by' => $actor->id,
        ])->save();
        $this->trail->record(AuditEvent::Updated, $record, null, ['configuration_items_added' => [$item->name]], $actor->id);
    }

    public function unlink(Model $record, string $itemId, User $actor): void
    {
        $link = CiLink::query()
            ->with('configurationItem')
            ->where('configuration_item_id', $itemId)
            ->where('linkable_type', $record->getMorphClass())
            ->where('linkable_id', $record->getKey())
            ->first();

        if ($link !== null) {
            $name = $link->configurationItem->name ?? $itemId;
            $link->delete();
            $this->trail->record(AuditEvent::Updated, $record, ['configuration_items_removed' => [$name]], null, $actor->id);
        }
    }

    public function impactOf(Model $record): array
    {
        $ids = [];

        foreach (CiLink::query()->where('linkable_type', $record->getMorphClass())->where('linkable_id', $record->getKey())->pluck('configuration_item_id') as $id) {
            if (is_string($id)) {
                $ids[] = $id;
            }
        }

        return array_values($this->impact->affectedBy($ids)
            ->map(fn (ImpactedItem $impacted): ConfigurationItemSummary => self::summary($impacted->item, $impacted->depth))
            ->all());
    }

    private static function summary(ConfigurationItem $item, int $depth = 0): ConfigurationItemSummary
    {
        return new ConfigurationItemSummary(
            $item->id,
            $item->name,
            $item->type->name,
            $item->criticality->value,
            $item->criticality->label(),
            route('cmdb.items.show', $item),
            $depth,
        );
    }
}
