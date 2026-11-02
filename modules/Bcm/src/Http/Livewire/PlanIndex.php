<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Bcm\Domain\Enums\PlanType;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bcm\Domain\Models\PlanVersion;

#[Layout('core::components.layouts.app')]
final class PlanIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $type = '';

    #[Url]
    public bool $retired = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Plan::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'type', 'retired'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $this->authorize('viewAny', Plan::class);
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim(mb_substr($this->search, 0, 200))).'%';

        $plans = Plan::query()
            ->with('owner')
            ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($inner) => $inner->whereLike('name', $like)->orWhereLike('description', $like)))
            ->when(PlanType::tryFrom($this->type) !== null, fn ($query) => $query->where('type', $this->type))
            ->when(! $this->retired, fn ($query) => $query->where('status', 'active'))
            ->orderBy('type')
            ->orderBy('name')
            ->paginate(25);

        return view('bcm::livewire.plans.index', [
            'plans' => $plans,
            'open' => PlanVersion::query()->whereIn('plan_id', $plans->getCollection()->modelKeys())->whereIn('status', ['draft', 'submitted', 'returned'])->get()->keyBy('plan_id'),
            'types' => PlanType::cases(),
        ])->title(__('bcm::plans.title'));
    }
}
