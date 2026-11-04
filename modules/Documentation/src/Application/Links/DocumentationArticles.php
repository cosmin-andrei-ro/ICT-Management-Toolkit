<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Application\Links;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Domain\Links\KnowledgeArticles;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\User;
use Modules\Documentation\Application\SpaceAccess;
use Modules\Documentation\Domain\Models\Page;
use Modules\Documentation\Domain\Models\PageLink;
use Modules\Documentation\Domain\Models\Space;

/**
 * Published pages of the spaces a person reads, for IT support and for
 * links from other modules (LinkedRecords "documentation.page").
 */
final readonly class DocumentationArticles implements KnowledgeArticles
{
    /** Words shorter than this say little about a problem ("the", "is"). */
    private const int MIN_WORD = 3;

    private const int MAX_WORDS = 10;

    public function __construct(private SpaceAccess $access) {}

    public function suggest(string $text, User $viewer, int $limit = 5): array
    {
        // Any of the words, ranked: a ticket title rarely contains every word of the article.
        $words = array_slice(array_values(array_unique(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(mb_substr($text, 0, 500))) ?: [],
            fn (string $word): bool => mb_strlen($word) >= self::MIN_WORD,
        ))), 0, self::MAX_WORDS);

        if ($words === []) {
            return [];
        }

        // Only letters and digits reach the query: no tsquery operator can be injected.
        $query = implode(' | ', array_map(fn (string $word): string => $word.':*', $words));

        return $this->records($this->readable($viewer)
            ->whereRaw("search_vector @@ to_tsquery('simple', ?)", [$query])
            ->orderByRaw("ts_rank(search_vector, to_tsquery('simple', ?)) DESC", [$query])
            ->limit(max(1, min($limit, 20)))
            ->get());
    }

    public function about(string $type, string $id, User $viewer): array
    {
        return $this->records($this->readable($viewer)
            ->whereIn('id', PageLink::query()->where('linked_type', $type)->where('linked_id', $id)->select('page_id'))
            ->orderBy('published_title')
            ->limit(50)
            ->get());
    }

    /**
     * @param  list<string>  $ids
     * @return list<LinkedRecord>
     */
    public function resolve(array $ids, User $viewer): array
    {
        return $this->records($this->readable($viewer)->whereKey($ids)->limit(100)->get());
    }

    /**
     * @return list<LinkedRecord>
     */
    public function search(string $term, User $viewer): array
    {
        $like = LinkedRecords::like($term);

        return $this->records($this->readable($viewer)
            ->when($term !== '', fn (Builder $query) => $query->whereLike('published_title', $like))
            ->latest('published_at')
            ->limit(20)
            ->get());
    }

    /**
     * @return Builder<Page>
     */
    private function readable(User $viewer): Builder
    {
        return Page::query()->with('space')
            ->whereNotNull('published_version')
            ->whereIn('space_id', Space::query()->tap(fn (Builder $query) => $this->access->readable($query, $viewer))->select('id'));
    }

    /**
     * @param  Collection<int, Page>  $pages
     * @return list<LinkedRecord>
     */
    private function records(Collection $pages): array
    {
        return array_values($pages->map(fn (Page $page): LinkedRecord => new LinkedRecord($page->id, $page->displayTitle(), route('documentation.pages.show', $page), $page->space->name))->all());
    }
}
