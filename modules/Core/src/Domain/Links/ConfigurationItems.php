<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Modules\Core\Domain\Models\User;

/**
 * Configuration items linked to records of other modules (a ticket's affected
 * items). Implemented by the CMDB module; when it is disabled, nothing is
 * available and nothing is linked.
 */
interface ConfigurationItems
{
    public function isAvailable(): bool;

    /**
     * @return list<ConfigurationItemSummary>
     */
    public function search(string $terms, int $limit = 10): array;

    /**
     * @return list<ConfigurationItemSummary>
     */
    public function linkedTo(Model $record): array;

    /**
     * Items of the organization by id; unknown ids are left out.
     *
     * @param  list<string>  $ids
     * @return array<string, ConfigurationItemSummary>
     */
    public function find(array $ids): array;

    /**
     * @throws InvalidArgumentException when the item is not one of the organization
     */
    public function link(Model $record, string $itemId, User $actor): void;

    public function unlink(Model $record, string $itemId, User $actor): void;

    /**
     * Items a failure of the linked items would affect, nearest first.
     *
     * @return list<ConfigurationItemSummary>
     */
    public function impactOf(Model $record): array;

    /**
     * Items a failure of these items would affect, nearest first (not the
     * items themselves): for modules that know items but not the record.
     *
     * @param  list<string>  $itemIds
     * @return list<ConfigurationItemSummary>
     */
    public function impactOfItems(array $itemIds): array;

    /**
     * Items linked to records of one kind, by record id, in one query.
     *
     * @param  list<string>  $recordIds
     * @return array<string, list<string>>
     */
    public function linkedIds(string $morphType, array $recordIds): array;

    /**
     * Records of one kind linked to any of these items (the recovery plans
     * covering an application's components), in one query.
     *
     * @param  list<string>  $itemIds
     * @return list<string> Record ids.
     */
    public function recordIdsLinkedTo(array $itemIds, string $morphType): array;

    /**
     * Items these items need to work, directly or not (not the items themselves).
     *
     * @param  list<string>  $itemIds
     * @return list<string>
     */
    public function dependencyIdsOf(array $itemIds): array;

    /**
     * @param  list<string>  $itemIds
     * @return array<string, RecoveryProfile> By item id; unknown ids are left out.
     */
    public function recoveryProfiles(array $itemIds): array;

    /**
     * Records that a recovery test brought the item back, the evidence behind
     * its declared recovery capability. The caller authorizes the test.
     *
     * @param  ?int  $measuredMinutes  How long it took; null when not measured.
     * @param  string  $evidence  What proved it (the plan and the exercise).
     *
     * @throws InvalidArgumentException for an item of another organization
     */
    public function confirmRecovery(string $itemId, ?int $measuredMinutes, CarbonImmutable $on, string $evidence, User $actor): void;

    /**
     * The item standing for a record of another module (an application of the
     * portfolio): the one given, else the one with this identifier, else a new
     * one of the given type. It is then related ("runs on") to the given items.
     *
     * @param  string  $criticality  low, medium, high or critical; used for a new item only
     * @param  list<string>  $runsOn  Items of the organization; others are ignored.
     * @return ?string The item's id; null when the CMDB has no type with this key.
     */
    public function ensureItem(?string $itemId, string $typeKey, string $identifier, string $name, string $criticality, array $runsOn, User $actor): ?string;

    public function canChangeCriticality(User $actor): bool;

    /**
     * @param  string  $criticality  low, medium, high or critical
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException when the actor may not change the item
     * @throws InvalidArgumentException for an unknown item or criticality
     */
    public function changeCriticality(string $itemId, string $criticality, User $actor): void;
}
