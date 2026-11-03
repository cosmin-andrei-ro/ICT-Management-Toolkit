<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Application\Links;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Modules\Cmdb\Application\Actions\ManageRelationships;
use Modules\Cmdb\Application\Actions\ProvisionDefaultTypes;
use Modules\Cmdb\Application\Impact\ImpactAnalysis;
use Modules\Cmdb\Application\Impact\ImpactedItem;
use Modules\Cmdb\Domain\Enums\Criticality;
use Modules\Cmdb\Domain\Enums\RelationshipType;
use Modules\Cmdb\Domain\Models\CiLink;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Links\ConfigurationItemSummary;
use Modules\Core\Domain\Links\RecoveryProfile;
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
        private Gate $gate,
        private ProvisionDefaultTypes $defaultTypes,
        private ManageRelationships $relationships,
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

    public function find(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $found = [];

        foreach (ConfigurationItem::query()->with('type')->whereKey($ids)->get() as $item) {
            $found[$item->id] = self::summary($item);
        }

        return $found;
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

    public function findByReference(string $reference): ?ConfigurationItemSummary
    {
        $reference = mb_strtolower(trim($reference));

        if ($reference === '') {
            return null;
        }

        // Retired items no longer run anywhere: an alert cannot be about them.
        foreach (['identifier', 'name'] as $column) {
            $matches = ConfigurationItem::query()->with('type')
                ->where('status', '!=', 'retired')
                ->whereRaw("lower({$column}) = ?", [$reference])
                ->limit(2)
                ->get();

            if ($matches->count() === 1) {
                return self::summary($matches->sole());
            }

            if ($matches->count() > 1) {
                return null;
            }
        }

        return null;
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

        return $this->impactOfItems($ids);
    }

    public function impactOfItems(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        return array_values($this->impact->affectedBy($itemIds)
            ->map(fn (ImpactedItem $impacted): ConfigurationItemSummary => self::summary($impacted->item, $impacted->depth))
            ->all());
    }

    public function linkedIds(string $morphType, array $recordIds): array
    {
        if ($recordIds === []) {
            return [];
        }

        $linked = [];

        foreach (CiLink::query()->where('linkable_type', $morphType)->whereIn('linkable_id', $recordIds)->get(['linkable_id', 'configuration_item_id']) as $link) {
            $linked[$link->linkable_id][] = $link->configuration_item_id;
        }

        return $linked;
    }

    public function recordIdsLinkedTo(array $itemIds, string $morphType): array
    {
        if ($itemIds === []) {
            return [];
        }

        return array_values(array_unique(array_filter(
            CiLink::query()->whereIn('configuration_item_id', $itemIds)->where('linkable_type', $morphType)->pluck('linkable_id')->all(),
            is_string(...),
        )));
    }

    public function dependencyIdsOf(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        return array_values(array_diff(
            array_values($this->impact->dependenciesOf($itemIds)->map(fn (ImpactedItem $needed): string => $needed->item->id)->all()),
            $itemIds,
        ));
    }

    public function recoveryProfiles(array $itemIds): array
    {
        $profiles = [];

        foreach (ConfigurationItem::query()->with('type')->whereKey($itemIds)->get() as $item) {
            $profiles[$item->id] = new RecoveryProfile(
                $item->id,
                $item->name,
                $item->type->name,
                route('cmdb.items.show', $item),
                $item->criticality->value,
                $item->recovery_time_minutes,
                $item->recovery_point_minutes,
                $item->recovery_verified_on?->toDateString(),
                $item->recovery_verified_minutes,
            );
        }

        return $profiles;
    }

    public function confirmRecovery(string $itemId, ?int $measuredMinutes, CarbonImmutable $on, string $evidence, User $actor): void
    {
        // Scoped lookup: an item of another organization is not found.
        $item = ConfigurationItem::query()->find($itemId) ?? throw new InvalidArgumentException('Unknown configuration item.');

        // Audited by the item's own trail, as any other change.
        $item->forceFill([
            'recovery_verified_on' => $on,
            'recovery_verified_minutes' => $measuredMinutes,
            'recovery_verified_by' => mb_substr($evidence, 0, 255),
            'updated_by' => $actor->id,
        ])->save();
    }

    public function ensureItem(?string $itemId, string $typeKey, string $identifier, string $name, string $criticality, array $runsOn, User $actor): ?string
    {
        // Scoped lookups: items of another organization are not found.
        $item = ($itemId !== null ? ConfigurationItem::query()->find($itemId) : null)
            ?? ConfigurationItem::query()->whereRaw('lower(identifier) = ?', [mb_strtolower($identifier)])->first();

        if ($item === null) {
            $this->defaultTypes->handle();
            $type = CiType::query()->where('key', $typeKey)->first();

            if ($type === null) {
                return null;
            }

            // Audited by the item's own trail.
            $item = new ConfigurationItem;
            $item->forceFill([
                'organization_id' => $type->organization_id,
                'type_id' => $type->id,
                'name' => mb_substr($name, 0, 255),
                'identifier' => mb_substr($identifier, 0, 255),
                'criticality' => Criticality::tryFrom($criticality) ?? Criticality::Medium,
                'created_by' => $actor->id,
            ])->save();
        }

        foreach (ConfigurationItem::query()->whereKey($runsOn)->get() as $target) {
            try {
                $this->relationships->add($item, RelationshipType::RunsOn, $target);
            } catch (InvalidArgumentException) {
                // The item itself, or a cycle the CMDB refuses: nothing to relate.
            }
        }

        return $item->id;
    }

    public function canChangeCriticality(User $actor): bool
    {
        return $actor->can('cmdb.ci.manage');
    }

    public function changeCriticality(string $itemId, string $criticality, User $actor): void
    {
        // Scoped lookup: an item of another organization is not found.
        $item = ConfigurationItem::query()->find($itemId) ?? throw new InvalidArgumentException('Unknown configuration item.');
        $value = Criticality::tryFrom($criticality) ?? throw new InvalidArgumentException('Unknown criticality.');

        if (! $this->gate->forUser($actor)->allows('update', $item)) {
            throw new AuthorizationException;
        }

        // Audited by the item's own trail, as any other change.
        $item->criticality = $value;
        $item->save();
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
