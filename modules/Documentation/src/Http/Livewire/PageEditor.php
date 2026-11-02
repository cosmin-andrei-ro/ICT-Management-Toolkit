<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Text\Markdown;
use Modules\Documentation\Application\Actions\PageChangedMeanwhile;
use Modules\Documentation\Application\Actions\SavePage;
use Modules\Documentation\Domain\Models\Page;
use Modules\Documentation\Domain\Models\Space;

/**
 * Writes a page in Markdown, with a toolbar and its preview side by side.
 */
#[Layout('core::components.layouts.app')]
final class PageEditor extends Component
{
    #[Locked]
    public string $spaceId = '';

    #[Locked]
    public ?string $pageId = null;

    /** The version the editor started from: saving over a newer one is refused. */
    #[Locked]
    public ?int $baseVersion = null;

    public string $title = '';

    public string $body = '';

    public string $parentId = '';

    public string $summary = '';

    public function mount(?Space $space = null, ?Page $page = null): void
    {
        if ($page !== null && $page->exists) {
            $this->authorize('update', $page);
            $this->spaceId = $page->space_id;
            $this->pageId = $page->id;
            $this->baseVersion = $page->version;
            $this->title = $page->title;
            $this->body = $page->body;
            $this->parentId = $page->parent_id ?? '';

            return;
        }

        abort_if($space === null || ! $space->exists, 404);
        $this->authorize('edit', $space);
        $this->spaceId = $space->id;
        $parent = request()->query('parent');
        $this->parentId = is_string($parent) && Page::query()->where('space_id', $space->id)->whereKey($parent)->exists() ? $parent : '';
    }

    public function save(SavePage $save): void
    {
        $space = Space::query()->findOrFail($this->spaceId);
        $page = $this->pageId !== null ? Page::query()->findOrFail($this->pageId) : null;
        $page !== null ? $this->authorize('update', $page) : $this->authorize('edit', $space);
        $max = config('documentation.pages.max_length');

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['present', 'string', 'max:'.(is_int($max) ? $max : 200000)],
            'parentId' => ['nullable', 'uuid'],
            'summary' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $saved = $save->handle($page, $space, $this->parentId !== '' ? $this->parentId : null, $this->title, $this->body, $this->summary, $this->baseVersion, $this->actor());
        } catch (PageChangedMeanwhile) {
            $this->addError('body', __('documentation::docs.pages.changed_meanwhile'));

            return;
        } catch (InvalidArgumentException) {
            $this->addError('parentId', __('documentation::docs.pages.bad_parent'));

            return;
        }

        $this->redirectRoute('documentation.pages.show', $saved);
    }

    public function render(): View
    {
        $space = Space::query()->findOrFail($this->spaceId);

        return view('documentation::livewire.editor', [
            'space' => $space,
            'parents' => $this->parents(),
            'preview' => Markdown::toSafeHtml($this->body),
        ])->title(__($this->pageId !== null ? 'documentation::docs.pages.edit' : 'documentation::docs.pages.new'));
    }

    /**
     * Pages it may go under: none of its own sub-pages.
     *
     * @return Collection<int, Page>
     */
    private function parents(): Collection
    {
        $pages = Page::query()->where('space_id', $this->spaceId)->orderBy('title')->get(['id', 'parent_id', 'title']);

        if ($this->pageId === null) {
            return $pages;
        }

        $excluded = [$this->pageId];

        do {
            $added = $pages->whereIn('parent_id', $excluded)->pluck('id')->diff($excluded)->all();
            $excluded = [...$excluded, ...$added];
        } while ($added !== []);

        return $pages->whereNotIn('id', $excluded)->values();
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
