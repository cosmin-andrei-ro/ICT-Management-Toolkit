<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Core\Domain\Models\User;
use Modules\Documentation\Application\Search;
use Modules\Documentation\Domain\Models\Page;
use Modules\Documentation\Domain\Models\Space;

/**
 * A space: its pages as a tree, and search within it.
 */
#[Layout('core::components.layouts.app')]
final class ShowSpace extends Component
{
    #[Locked]
    public string $spaceId = '';

    #[Url]
    public string $q = '';

    public function mount(Space $space): void
    {
        $this->authorize('view', $space);
        $this->spaceId = $space->id;
    }

    public function render(Search $search): View
    {
        $space = Space::query()->with(['owners', 'teams'])->findOrFail($this->spaceId);
        $this->authorize('view', $space);
        $viewer = $this->actor();
        $canEdit = $viewer->can('edit', $space);
        // Pages never published stay with the space's editors.
        $pages = Page::query()->where('space_id', $space->id)->when(! $canEdit, fn ($query) => $query->whereNotNull('published_version'))
            ->orderBy('position')->orderBy('title')->get(['id', 'parent_id', 'title', 'published_title', 'published_version', 'version', 'pending_version', 'review_on', 'updated_at']);
        $today = CarbonImmutable::today();

        return view('documentation::livewire.space', [
            'space' => $space,
            'tree' => self::tree($pages, null, 0),
            'results' => trim($this->q) !== '' ? $search->pages($this->q, $viewer, $space->id) : null,
            'canEdit' => $canEdit,
            'toApprove' => $viewer->can('approve', $space) ? $pages->whereNotNull('pending_version')->values() : collect(),
            'toReview' => $canEdit ? $pages->filter(fn (Page $page): bool => $page->isReviewOverdue($today) || ($page->review_on !== null && $page->review_on->lte($today->addDays(30))))->sortBy('review_on')->values() : collect(),
            'today' => $today,
        ])->title($space->name);
    }

    /**
     * The pages in reading order, each with its depth.
     *
     * @param  Collection<int, Page>  $pages
     * @return list<array{page: Page, depth: int}>
     */
    private static function tree(Collection $pages, ?string $parentId, int $depth): array
    {
        $rows = [];

        foreach ($pages->where('parent_id', $parentId) as $page) {
            $rows[] = ['page' => $page, 'depth' => $depth];
            // Depth is bounded: a tree deeper than this is shown flat below it.
            $rows = [...$rows, ...($depth < 20 ? self::tree($pages, $page->id, $depth + 1) : [])];
        }

        return $rows;
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
