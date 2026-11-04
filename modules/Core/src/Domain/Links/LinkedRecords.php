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
    public const int MIN_SEARCH_LENGTH = 2;

    /** @var array<string, Closure(list<string>, User): list<LinkedRecord>> */
    private array $resolvers = [];

    /** @var array<string, array{label: string, search: Closure(string, User): list<LinkedRecord>}> */
    private array $searches = [];

    /** @var array<string, array{label: string, search: Closure(string, User): list<LinkedRecord>}> */
    private array $globalOnly = [];

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
     * Found by the global search only: it cannot be picked as evidence or a deliverable.
     *
     * @param  string  $label  Translation key naming the kind of record.
     * @param  Closure(string, User): list<LinkedRecord>  $search  What matches and the viewer may see.
     */
    public function registerGlobalSearch(string $morphType, string $label, Closure $search): void
    {
        $this->globalOnly[$morphType] = ['label' => $label, 'search' => $search];
    }

    /**
     * Every kind of record matching the term, for the search in the page header.
     *
     * @return array<string, list<LinkedRecord>> Translated label => records, by label, without empty kinds.
     */
    public function searchEverywhere(string $term, User $viewer, int $perKind = 8): array
    {
        $term = trim(mb_substr($term, 0, 200));

        if (mb_strlen($term) < self::MIN_SEARCH_LENGTH) {
            return [];
        }

        $found = [];

        foreach ([...$this->searches, ...$this->globalOnly] as $search) {
            $records = array_slice($search['search']($term, $viewer), 0, $perKind);

            if ($records !== []) {
                $found[__($search['label'])] = $records;
            }
        }

        ksort($found);

        return $found;
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
     * What the kind of record is called, or its type if it has no search.
     */
    public function kindLabel(string $morphType): string
    {
        $label = $this->searches[$morphType]['label'] ?? $this->globalOnly[$morphType]['label'] ?? null;

        return $label !== null ? __($label) : $morphType;
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
