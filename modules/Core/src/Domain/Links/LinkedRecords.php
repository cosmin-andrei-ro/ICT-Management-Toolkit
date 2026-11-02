<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

use Closure;
use Modules\Core\Domain\Models\User;

/**
 * How to show records of other modules by morph type and id (e.g. the tickets
 * linked to a configuration item), and how to find them by name for modules
 * linking to them (e.g. compliance evidence). Each module registers its own
 * types and returns only what the viewer may see.
 */
final class LinkedRecords
{
    /** @var array<string, Closure(list<string>, User): list<LinkedRecord>> */
    private array $resolvers = [];

    /** @var array<string, array{label: string, search: Closure(string, User): list<LinkedRecord>}> */
    private array $searches = [];

    /**
     * @param  Closure(list<string>, User): list<LinkedRecord>  $resolver
     */
    public function register(string $morphType, Closure $resolver): void
    {
        $this->resolvers[$morphType] = $resolver;
    }

    /**
     * @param  string  $label  Translation key naming the kind of record.
     * @param  Closure(string, User): list<LinkedRecord>  $search  At most 20 records matching the term (the latest ones for an empty term) the viewer may see.
     */
    public function registerSearch(string $morphType, string $label, Closure $search): void
    {
        $this->searches[$morphType] = ['label' => $label, 'search' => $search];
    }

    /**
     * @return array<string, string> Morph type => translated label, of the kinds that can be searched, by label.
     */
    public function searchable(): array
    {
        $labels = array_map(fn (array $search): string => __($search['label']), $this->searches);
        asort($labels);

        return $labels;
    }

    /**
     * @return list<LinkedRecord>
     */
    public function search(string $morphType, string $term, User $viewer): array
    {
        $search = $this->searches[$morphType]['search'] ?? null;

        return $search !== null ? array_slice($search(trim(mb_substr($term, 0, 200)), $viewer), 0, 20) : [];
    }

    /**
     * A LIKE pattern matching the term anywhere, for search closures.
     */
    public static function like(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
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
