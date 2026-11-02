<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Domain\Models\Location;
use Modules\Dcim\Application\PowerGrid;
use Modules\Dcim\Domain\Models\PowerSource;
use Modules\Dcim\Domain\Models\Rack;

/**
 * Feeds, UPSs and PDUs of every room, as a chain, with their load.
 */
#[Layout('core::components.layouts.app')]
final class PowerIndex extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', Rack::class);
    }

    public function render(): View
    {
        $grid = PowerGrid::read();
        /** @var array<string, list<PowerSource>> $children */
        $children = [];

        foreach (PowerSource::query()->with('rack')->orderBy('name')->get() as $source) {
            $children[$source->upstream_id ?? ''][] = $source;
        }

        $rows = [];
        $walk = function (string $parentId, int $depth) use (&$walk, &$rows, $children, $grid): void {
            foreach ($depth > 20 ? [] : ($children[$parentId] ?? []) as $source) {
                $rows[$source->location_id][] = ['source' => $source, 'depth' => $depth, 'load' => $grid->loadOf($source)];
                $walk($source->id, $depth + 1);
            }
        };
        $walk('', 0);

        return view('dcim::livewire.power-index', [
            'rooms' => $rows,
            'paths' => Location::paths(false),
            'warn' => config()->integer('dcim.power.warn_percent', 80),
            'singleFed' => count($grid->singleFed()),
        ])->title(__('dcim::power.title'));
    }
}
