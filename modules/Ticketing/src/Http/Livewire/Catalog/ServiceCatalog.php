<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Livewire\Catalog;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Ticketing\Domain\Models\Service;
use Modules\Ticketing\Domain\Models\Ticket;

#[Layout('core::components.layouts.app')]
final class ServiceCatalog extends Component
{
    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('create', Ticket::class);
    }

    public function render(): View
    {
        $terms = trim(mb_substr($this->search, 0, 100));

        $services = Service::query()
            ->where('is_active', true)
            ->when($terms !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->whereRaw('name ILIKE ?', ['%'.addcslashes($terms, '%_\\').'%'])
                ->orWhereRaw('description ILIKE ?', ['%'.addcslashes($terms, '%_\\').'%'])))
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('ticketing::livewire.catalog.index', ['services' => $services])
            ->title(__('ticketing::catalog.title'));
    }
}
