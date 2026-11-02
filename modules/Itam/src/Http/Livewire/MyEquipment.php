<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Domain\Models\User;
use Modules\Itam\Application\Actions\AcknowledgeHandover;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\AssetAssignment;
use Modules\Itam\Domain\Models\LicenseAssignment;

/**
 * What the signed-in person has from IT, for everyone. No costs, invoices or
 * notes: only what identifies the equipment and since when they have it.
 */
#[Layout('core::components.layouts.app')]
final class MyEquipment extends Component
{
    public function confirmReceipt(string $assignmentId, AcknowledgeHandover $acknowledge): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        // Only the person's own open assignments are found; the action checks again under lock.
        $assignment = AssetAssignment::query()->where('user_id', $user->id)->whereNull('returned_at')->findOrFail($assignmentId);
        $acknowledge->handle($assignment, $user, request()->ip());
    }

    public function render(): View
    {
        $userId = auth()->id();

        $assets = Asset::query()
            ->with('category')
            ->where('assigned_to_id', $userId)
            ->orderBy('asset_tag')
            ->get();

        $assignments = AssetAssignment::query()
            ->whereIn('asset_id', $assets->modelKeys())
            ->where('user_id', $userId)
            ->whereNull('returned_at')
            ->get()
            ->keyBy('asset_id');

        return view('itam::livewire.my-equipment', [
            // Product and licence name only: no keys, no costs.
            'software' => LicenseAssignment::query()->with('license.product')->where('user_id', $userId)->get()
                ->map(fn (LicenseAssignment $seat): string => $seat->license->product->name)->unique()->sort()->values(),
            'assets' => $assets,
            'assignments' => $assignments,
        ])->title(__('itam::assets.my.title'));
    }
}
