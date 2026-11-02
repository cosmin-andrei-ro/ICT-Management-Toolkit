<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Links\PhysicalAssets;
use Modules\Dcim\Domain\Models\Cable;
use Modules\Dcim\Domain\Models\Rack;

/**
 * Every cable, findable by its label.
 */
#[Layout('core::components.layouts.app')]
final class CableIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Rack::class);
    }

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function render(PhysicalAssets $assets): View
    {
        $cables = Cable::query()->with('ends.port.mount.rack')
            ->when(trim($this->q) !== '', fn ($query) => $query->whereLike('label', LinkedRecords::like(trim($this->q))))
            ->orderByRaw('lower(label) NULLS LAST')
            ->paginate(50);

        $ids = [];

        foreach ($cables as $cable) {
            foreach ($cable->ends as $end) {
                $ids[] = $end->port->mount->asset_id;
            }
        }

        return view('dcim::livewire.cables', [
            'cables' => $cables,
            'labels' => $assets->find(array_values(array_unique($ids))),
        ])->title(__('dcim::cabling.cables'));
    }
}
