<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\User;
use Modules\Projects\Domain\Enums\ProjectStatus;
use Modules\Projects\Domain\Models\Project;

/**
 * The projects a person may see, open ones first.
 */
#[Layout('core::components.layouts.app')]
final class ProjectIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    /** "open", "closed" or "" for all. */
    #[Url]
    public string $state = 'open';

    public function mount(): void
    {
        $this->authorize('viewAny', Project::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['q', 'state'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);
        $seesAll = $user->can('projects.project.view') || $user->can('projects.project.manage');

        $projects = Project::query()->with(['manager', 'phases'])->withCount(['tasks', 'tasks as done_count' => fn (Builder $query) => $query->where('status', 'done')])
            ->when(! $seesAll, fn ($query) => $query->involving($user))
            ->when($this->state === 'open', fn ($query) => $query->whereIn('status', [ProjectStatus::Active->value, ProjectStatus::OnHold->value]))
            ->when($this->state === 'closed', fn ($query) => $query->whereIn('status', [ProjectStatus::Closed->value, ProjectStatus::Cancelled->value]))
            ->when(trim($this->q) !== '', fn ($query) => $query->where(fn ($match) => $match->whereLike('name', LinkedRecords::like(trim($this->q)))->orWhereLike('code', LinkedRecords::like(trim($this->q)))))
            ->orderBy('planned_end')->orderBy('code')
            ->paginate(25);

        return view('projects::livewire.index', ['projects' => $projects])->title(__('projects::projects.title'));
    }
}
