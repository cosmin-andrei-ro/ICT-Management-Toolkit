<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\AssetAssignment;

/**
 * What the signed-in person has from IT, for everyone. No costs, invoices or
 * notes: only what identifies the equipment and since when they have it.
 */
#[Layout('core::components.layouts.app')]
final class MyEquipment extends Component
{
    public function render(): View
    {
        $userId = auth()->id();

        $assets = Asset::query()
            ->with('category')
            ->where('assigned_to_id', $userId)
            ->orderBy('asset_tag')
            ->get();

        $since = AssetAssignment::query()
            ->whereIn('asset_id', $assets->modelKeys())
            ->where('user_id', $userId)
            ->whereNull('returned_at')
            ->pluck('assigned_at', 'asset_id');

        return view('itam::livewire.my-equipment', [
            'assets' => $assets,
            'since' => $since,
        ])->title(__('itam::assets.my.title'));
    }
}
