<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

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
}
