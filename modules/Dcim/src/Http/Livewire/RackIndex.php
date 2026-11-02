<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Domain\Models\Location;
use Modules\Dcim\Domain\Models\Rack;

/**
 * The racks of every room, with how full they are.
 */
#[Layout('core::components.layouts.app')]
final class RackIndex extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', Rack::class);
    }

    public function render(): View
    {
        $paths = Location::paths(false);
        $racks = Rack::query()->with('mounts')->get()
            ->sortBy(fn (Rack $rack): string => sprintf('%s|%s|%05d|%s', $paths[$rack->location_id] ?? '', $rack->row ?? '', $rack->position ?? 0, $rack->name), SORT_NATURAL | SORT_FLAG_CASE)
            ->groupBy('location_id');

        return view('dcim::livewire.index', [
            'rooms' => $racks,
            'paths' => $paths,
        ])->title(__('dcim::racks.title'));
    }
}
