<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Core\Domain\Models\User;
use Modules\Documentation\Application\Actions\PageChangedMeanwhile;
use Modules\Documentation\Application\Actions\SavePage;
use Modules\Documentation\Application\Diff\LineDiff;
use Modules\Documentation\Domain\Models\Page;
use Modules\Documentation\Domain\Models\PageVersion;

/**
 * The versions of a page, what each changed, and going back to one.
 */
#[Layout('core::components.layouts.app')]
final class PageHistory extends Component
{
    #[Locked]
    public string $pageId = '';

    #[Url]
    public int $version = 0;

    public function mount(Page $page): void
    {
        $this->authorize('view', $page);
        $this->pageId = $page->id;
        $this->version = $this->version > 0 ? $this->version : $page->version;
    }

    public function restore(int $version, SavePage $save): void
    {
        $page = Page::query()->with('space')->findOrFail($this->pageId);
        $this->authorize('update', $page);
        $old = PageVersion::query()->where('page_id', $page->id)->where('version', $version)->firstOrFail();

        try {
            $save->handle($page, $page->space, $page->parent_id, $old->title, $old->body, __('documentation::docs.history.restored_summary', ['version' => $version]), $page->version, $this->actor());
        } catch (PageChangedMeanwhile) {
            return;
        }

        $this->redirectRoute('documentation.pages.show', $page);
    }

    public function render(): View
    {
        $page = Page::query()->findOrFail($this->pageId);
        $this->authorize('view', $page);
        $versions = $page->versions()->with('author')->get(['id', 'page_id', 'version', 'title', 'summary', 'author_id', 'created_at']);
        $selected = PageVersion::query()->where('page_id', $page->id)->where('version', $this->version)->first();
        $previous = $selected !== null ? PageVersion::query()->where('page_id', $page->id)->where('version', '<', $selected->version)->orderByDesc('version')->first() : null;

        return view('documentation::livewire.history', [
            'page' => $page,
            'versions' => $versions,
            'selected' => $selected,
            'previous' => $previous,
            'diff' => $selected !== null ? LineDiff::between($previous !== null ? $previous->body : '', $selected->body) : [],
            'canEdit' => $this->actor()->can('update', $page),
            'timezone' => config()->string('app.display_timezone'),
        ])->title(__('documentation::docs.history.title', ['page' => $page->title]));
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
