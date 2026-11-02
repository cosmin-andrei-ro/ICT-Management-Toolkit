<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Domain\Models\User;
use Modules\Documentation\Application\Search;
use Modules\Documentation\Application\SpaceAccess;
use Modules\Documentation\Domain\Models\Page;
use Modules\Documentation\Domain\Models\Space;

/**
 * The spaces a person reads, what changed lately, and search across them.
 */
#[Layout('core::components.layouts.app')]
final class DocsIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Space::class);
    }

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function render(SpaceAccess $access, Search $search): View
    {
        $user = $this->actor();
        $readable = fn (Builder $query) => $access->readable($query, $user);
        $perPage = config('documentation.pages.per_page');

        return view('documentation::livewire.index', [
            'spaces' => Space::query()->tap($readable)->withCount('pages')->orderBy('name')->get(),
            'recent' => trim($this->q) === '' ? Page::query()->with(['space', 'editor'])->whereIn('space_id', Space::query()->tap($readable)->select('id'))->latest('updated_at')->limit(10)->get() : collect(),
            'results' => trim($this->q) !== '' ? $search->pages($this->q, $user, null, is_int($perPage) ? $perPage : 20) : null,
        ])->title(__('documentation::docs.title'));
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
