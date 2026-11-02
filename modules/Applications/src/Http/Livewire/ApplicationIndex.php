<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Applications\Application\Options\OptionCatalog;
use Modules\Applications\Domain\Models\Application;
use Modules\Applications\Domain\Models\Sheet;

#[Layout('core::components.layouts.app')]
final class ApplicationIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $criticality = '';

    #[Url]
    public bool $due = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Application::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'criticality', 'due'], true)) {
            $this->resetPage();
        }
    }

    public function render(OptionCatalog $options): View
    {
        $this->authorize('viewAny', Application::class);
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim(mb_substr($this->search, 0, 200))).'%';

        $applications = Application::query()
            ->with(['department', 'businessOwner', 'technicalOwner'])
            ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($inner) => $inner->whereLike('name', $like)->orWhereLike('code', $like)))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->criticality !== '', fn ($query) => $query->where('criticality', $this->criticality))
            ->when($this->due, fn ($query) => $query->where(fn ($inner) => $inner->whereNull('approved_at')->orWhere('next_review_on', '<', today()->toDateString())))
            ->orderBy('name')
            ->paginate(25);

        return view('applications::livewire.applications.index', [
            'applications' => $applications,
            'open' => Sheet::query()->whereIn('application_id', $applications->getCollection()->modelKeys())->whereIn('status', ['draft', 'submitted', 'returned'])->get()->keyBy('application_id'),
            'statuses' => $options->choices('application_status'),
            'criticalities' => $options->choices('business_criticality'),
            'options' => $options,
        ])->title(__('applications::applications.title'));
    }
}
