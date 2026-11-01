<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

use Closure;
use Modules\Core\Domain\Models\User;

/**
 * How to show records of other modules by morph type and id (e.g. the tickets
 * linked to a configuration item). Each module registers its own types and
 * returns only what the viewer may see.
 */
final class LinkedRecords
{
    /** @var array<string, Closure(list<string>, User): list<LinkedRecord>> */
    private array $resolvers = [];

    /**
     * @param  Closure(list<string>, User): list<LinkedRecord>  $resolver
     */
    public function register(string $morphType, Closure $resolver): void
    {
        $this->resolvers[$morphType] = $resolver;
    }

    /**
     * @param  list<string>  $ids
     * @return list<LinkedRecord>
     */
    public function resolve(string $morphType, array $ids, User $viewer): array
    {
        $resolver = $this->resolvers[$morphType] ?? null;

        return $resolver !== null && $ids !== [] ? $resolver($ids, $viewer) : [];
    }
}
