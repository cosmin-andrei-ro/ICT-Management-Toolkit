<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Application\Exports\RequestExport;
use Modules\Core\Domain\Exports\ExportFormat;
use Modules\Core\Domain\Exports\ExportStatus;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\User;
use Modules\Dcim\Application\Exports\DcimExport;
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

    public function export(string $format, RequestExport $request): void
    {
        $this->authorize('viewAny', Rack::class);
        $user = auth()->user();
        $format = ExportFormat::tryFrom($format);

        if (! $user instanceof User || $format === null) {
            return;
        }

        // RequestExport checks the permission and records the export in the audit trail.
        $request->handle($user, DcimExport::TYPE, $format, []);
    }

    public function render(): View
    {
        $paths = Location::paths(false);
        $racks = Rack::query()->with('mounts')->get()
            ->sortBy(fn (Rack $rack): string => sprintf('%s|%s|%05d|%s', $paths[$rack->location_id] ?? '', $rack->row ?? '', $rack->position ?? 0, $rack->name), SORT_NATURAL | SORT_FLAG_CASE)
            ->groupBy('location_id');

        $exports = Export::query()->where('user_id', auth()->id())->where('type', DcimExport::TYPE)->latest()->limit(3)->get();

        return view('dcim::livewire.index', [
            'exports' => $exports,
            'preparing' => $exports->contains(fn (Export $export): bool => $export->status === ExportStatus::Pending),
            'timezone' => config()->string('app.display_timezone'),
            'rooms' => $racks,
            'paths' => $paths,
        ])->title(__('dcim::racks.title'));
    }
}
