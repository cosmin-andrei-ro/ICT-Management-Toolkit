<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Audit\Domain\Enums\ControlStatus;
use Modules\Audit\Domain\Models\Control;

/**
 * The organization's controls, their owners and how far they are implemented.
 */
#[Layout('core::components.layouts.app')]
final class ControlIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public bool $unmapped = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Control::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'unmapped'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $this->authorize('viewAny', Control::class);
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim(mb_substr($this->search, 0, 200))).'%';
        $perPage = config('audit.compliance.per_page');

        return view('audit::livewire.controls.index', [
            'controls' => Control::query()
                ->with('owner')
                ->withCount('requirements')
                ->when(ControlStatus::tryFrom($this->status) !== null, fn (Builder $query) => $query->where('status', $this->status))
                ->when($this->unmapped, fn (Builder $query) => $query->whereDoesntHave('requirements'))
                ->when(trim($this->search) !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->whereLike('name', $like)->orWhereLike('description', $like)))
                ->orderBy('number')
                ->paginate(is_int($perPage) ? $perPage : 25),
            'statuses' => ControlStatus::cases(),
        ])->title(__('audit::compliance.controls.title'));
    }
}
