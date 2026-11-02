<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Livewire\Suppliers;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Domain\Models\Supplier;

#[Layout('core::components.layouts.app')]
final class SupplierIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public bool $inactive = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Supplier::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'inactive'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $this->authorize('viewAny', Supplier::class);

        return view('core::livewire.suppliers.index', [
            'suppliers' => Supplier::query()
                ->search(mb_substr($this->search, 0, 200))
                ->when(! $this->inactive, fn ($query) => $query->where('is_active', true))
                ->orderBy('name')
                ->paginate(25),
        ])->title(__('core::suppliers.title'));
    }
}
