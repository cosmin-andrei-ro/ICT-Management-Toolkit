<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Livewire\Contracts;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Domain\Enums\ContractStatus;
use Modules\Core\Domain\Enums\ContractType;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Supplier;

#[Layout('core::components.layouts.app')]
final class ContractIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $type = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $supplier = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Contract::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'type', 'status', 'supplier'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $this->authorize('viewAny', Contract::class);
        $today = CarbonImmutable::today();
        $expiringDays = Contract::expiringDays();
        $status = ContractStatus::tryFrom($this->status);

        $contracts = Contract::query()
            ->with('supplier')
            ->search(mb_substr($this->search, 0, 200))
            ->when(ContractType::tryFrom($this->type) !== null, fn ($query) => $query->where('type', $this->type))
            ->when($this->supplier !== '' && Str::isUuid($this->supplier), fn ($query) => $query->where('supplier_id', $this->supplier))
            ->when($status, fn ($query, ContractStatus $status) => $query->withStatus($status, $today, $expiringDays))
            // Nearest deadline first; contracts without an end last.
            ->orderByRaw('end_date - COALESCE(notice_days, 0) ASC NULLS LAST')
            ->orderBy('title')
            ->paginate(25);

        return view('core::livewire.contracts.index', [
            'contracts' => $contracts,
            'today' => $today,
            'expiringDays' => $expiringDays,
            'types' => ContractType::cases(),
            'statuses' => ContractStatus::cases(),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
        ])->title(__('core::contracts.title'));
    }
}
