<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Domain\Links\PhysicalAssets;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\User;
use Modules\Dcim\Application\Actions\MountAsset;
use Modules\Dcim\Application\Actions\RemoveFromRack;
use Modules\Dcim\Application\Elevation;
use Modules\Dcim\Domain\Enums\MountFace;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\Rack;

/**
 * A rack from the front and from the rear, what is mounted in it, and the
 * form to mount or move equipment.
 *
 * @property-read Rack $rack Computed.
 */
#[Layout('core::components.layouts.app')]
final class ShowRack extends Component
{
    #[Locked]
    public string $rackId = '';

    /** The mount being moved, or null for new equipment. */
    #[Locked]
    public ?string $editingId = null;

    public string $assetSearch = '';

    public string $assetId = '';

    public string $positionU = '';

    public string $heightU = '1';

    public string $face = 'front';

    public bool $fullDepth = true;

    public string $powerWatts = '';

    public string $notes = '';

    public function mount(Rack $rack): void
    {
        $this->authorize('view', $rack);
        $this->rackId = $rack->id;
    }

    #[Computed]
    public function rack(): Rack
    {
        return Rack::query()->with(['location', 'mounts'])->findOrFail($this->rackId);
    }

    public function chooseAsset(string $assetId): void
    {
        $this->authorize('update', $this->rack);
        $this->assetId = $assetId;
        $this->assetSearch = '';
    }

    public function clearAsset(): void
    {
        $this->assetId = '';
    }

    /**
     * Prefills the form for a free unit clicked on the drawing.
     */
    public function startAt(int $unit, string $face): void
    {
        $this->authorize('update', $this->rack);
        $this->cancel();
        $this->positionU = (string) $unit;
        $this->face = (MountFace::tryFrom($face) ?? MountFace::Front)->value;
    }

    public function edit(string $mountId): void
    {
        $this->authorize('update', $this->rack);
        $mount = $this->rack->mounts->firstWhere('id', $mountId) ?? abort(404);
        $this->resetErrorBag();
        $this->editingId = $mount->id;
        $this->assetId = $mount->asset_id;
        $this->positionU = (string) ($mount->position_u ?? '');
        $this->heightU = (string) $mount->height_u;
        $this->face = $mount->face->value;
        $this->fullDepth = $mount->full_depth;
        $this->powerWatts = (string) ($mount->power_watts ?? '');
        $this->notes = $mount->notes ?? '';
    }

    public function cancel(): void
    {
        $this->resetErrorBag();
        $this->reset('editingId', 'assetSearch', 'assetId', 'positionU', 'heightU', 'face', 'fullDepth', 'powerWatts', 'notes');
    }

    public function save(MountAsset $mountAsset): void
    {
        $rack = $this->rack;
        $this->authorize('update', $rack);
        $this->resetErrorBag();

        $this->validate([
            'assetId' => ['required', 'uuid'],
            'positionU' => ['nullable', 'integer', 'min:1', 'max:60'],
            'heightU' => ['required', 'integer', 'min:1', 'max:60'],
            'face' => ['required', Rule::enum(MountFace::class)],
            'fullDepth' => ['boolean'],
            'powerWatts' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $mountAsset->handle(
            $rack,
            $this->editingId !== null ? ($rack->mounts->firstWhere('id', $this->editingId) ?? abort(404)) : null,
            $this->assetId,
            $this->positionU !== '' ? (int) $this->positionU : null,
            (int) $this->heightU,
            MountFace::from($this->face),
            $this->fullDepth,
            $this->powerWatts !== '' ? (int) $this->powerWatts : null,
            trim($this->notes) !== '' ? trim($this->notes) : null,
            $this->actor(),
        );

        $this->cancel();
        unset($this->rack);
    }

    public function unmount(string $mountId, RemoveFromRack $remove): void
    {
        $this->authorize('update', $this->rack);
        $remove->unmount($this->rack->mounts->firstWhere('id', $mountId) ?? abort(404));
        $this->cancel();
        unset($this->rack);
    }

    public function deleteRack(RemoveFromRack $remove): void
    {
        $this->authorize('delete', $this->rack);
        $remove->deleteRack($this->rack);
        $this->redirectRoute('dcim.racks.index');
    }

    public function render(PhysicalAssets $assets): View
    {
        $rack = $this->rack;
        $search = trim($this->assetSearch);
        $ids = $rack->mounts->map(fn (Mount $mount): string => $mount->asset_id)->values()->all();
        $mounted = $assets->find(array_values(array_unique($this->assetId !== '' ? [...$ids, $this->assetId] : $ids)));
        $power = $rack->mounts->sum(fn (Mount $mount): int => $mount->power_watts ?? 0);

        return view('dcim::livewire.rack', [
            'rack' => $rack,
            'room' => Location::paths(false)[$rack->location_id] ?? $rack->location->name,
            'front' => Elevation::of($rack, MountFace::Front),
            'rear' => Elevation::of($rack, MountFace::Rear),
            'assets' => $mounted,
            'mounts' => $rack->mounts->sortByDesc(fn (Mount $mount): int => $mount->position_u ?? 0)->values(),
            'used' => $rack->usedUnits(),
            'power' => $power,
            'canManage' => $this->actor()->can('update', $rack),
            'candidates' => mb_strlen($search) >= 2 ? $assets->search($search, 10) : [],
            'faces' => MountFace::cases(),
        ])->title(__('dcim::racks.rack', ['name' => $rack->name]));
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
