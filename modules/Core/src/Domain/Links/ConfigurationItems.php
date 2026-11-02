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
     * Items linked to records of one kind, by record id, in one query.
     *
     * @param  list<string>  $recordIds
     * @return array<string, list<string>>
     */
    public function linkedIds(string $morphType, array $recordIds): array;

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

    public function canChangeCriticality(User $actor): bool;

    /**
     * @param  string  $criticality  low, medium, high or critical
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException when the actor may not change the item
     * @throws InvalidArgumentException for an unknown item or criticality
     */
    public function changeCriticality(string $itemId, string $criticality, User $actor): void;
}
