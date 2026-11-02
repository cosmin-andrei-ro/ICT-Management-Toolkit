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
use Modules\Core\Domain\Models\User;
use Modules\Dcim\Application\Actions\AddPorts;
use Modules\Dcim\Application\Actions\ConnectPorts;
use Modules\Dcim\Application\Actions\DeletePort;
use Modules\Dcim\Application\CablePath;
use Modules\Dcim\Domain\Enums\CableStatus;
use Modules\Dcim\Domain\Enums\CableType;
use Modules\Dcim\Domain\Enums\MountFace;
use Modules\Dcim\Domain\Enums\PortKind;
use Modules\Dcim\Domain\Models\Cable;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\Port;

/**
 * Mounted equipment: its ports, the cables on them, and where each
 * connection leads.
 *
 * @property-read Mount $equipment Computed.
 */
#[Layout('core::components.layouts.app')]
final class ShowEquipment extends Component
{
    #[Locked]
    public string $mountId = '';

    public string $pattern = '';

    public string $kind = 'rj45';

    public string $side = 'front';

    public bool $patchPanel = false;

    /** The port a cable is being laid from, or whose cable is being edited. */
    #[Locked]
    public ?string $connectingPortId = null;

    #[Locked]
    public ?string $editingCableId = null;

    public string $equipmentSearch = '';

    public string $otherMountId = '';

    public string $otherPortId = '';

    public string $cableLabel = '';

    public string $cableType = 'cat6a';

    public string $cableColour = '';

    public string $cableLength = '';

    public string $cableStatus = 'connected';

    public string $cableNotes = '';

    public ?string $tracingPortId = null;

    public function mount(Mount $mount): void
    {
        $this->authorize('view', $mount->rack);
        $this->mountId = $mount->id;
    }

    #[Computed]
    public function equipment(): Mount
    {
        return Mount::query()->with(['rack.location', 'ports.cableEnd.cable.ends.port.mount.rack', 'ports.rearPort', 'ports.frontPort'])->findOrFail($this->mountId);
    }

    public function addPorts(AddPorts $add): void
    {
        $this->authorizeManage();
        $this->resetErrorBag();
        $this->validate([
            'pattern' => ['required', 'string', 'max:80'],
            'kind' => ['required', Rule::enum(PortKind::class)],
            'side' => ['required', Rule::enum(MountFace::class)],
            'patchPanel' => ['boolean'],
        ]);

        $add->handle($this->equipment, $this->pattern, PortKind::from($this->kind), MountFace::from($this->side), $this->patchPanel);
        $this->reset('pattern', 'patchPanel');
        unset($this->equipment);
    }

    public function deletePort(string $portId, DeletePort $delete): void
    {
        $this->authorizeManage();
        $this->resetErrorBag();
        $delete->handle($this->port($portId));
        unset($this->equipment);
    }

    public function startConnecting(string $portId): void
    {
        $this->authorizeManage();
        $this->cancelCable();
        $this->connectingPortId = $this->port($portId)->id;
    }

    public function editCable(string $portId): void
    {
        $this->authorizeManage();
        $port = $this->port($portId);
        $cable = $port->cableEnd->cable ?? abort(404);
        $this->cancelCable();
        $this->connectingPortId = $port->id;
        $this->editingCableId = $cable->id;
        $this->cableLabel = $cable->label ?? '';
        $this->cableType = $cable->type->value;
        $this->cableColour = $cable->colour ?? '';
        $this->cableLength = $cable->length_m ?? '';
        $this->cableStatus = $cable->status->value;
        $this->cableNotes = $cable->notes ?? '';
    }

    public function chooseEquipment(string $mountId): void
    {
        $this->authorizeManage();
        $this->otherMountId = Mount::query()->findOrFail($mountId)->id;
        $this->otherPortId = '';
        $this->equipmentSearch = '';
    }

    public function cancelCable(): void
    {
        $this->resetErrorBag();
        $this->reset('connectingPortId', 'editingCableId', 'equipmentSearch', 'otherMountId', 'otherPortId', 'cableLabel', 'cableType', 'cableColour', 'cableLength', 'cableStatus', 'cableNotes');
    }

    public function saveCable(ConnectPorts $connect): void
    {
        $this->authorizeManage();
        $this->resetErrorBag();
        $port = $this->port((string) $this->connectingPortId);

        $this->validate([
            'otherPortId' => $this->editingCableId === null ? ['required', 'uuid'] : ['nullable'],
            'cableLabel' => ['nullable', 'string', 'max:64'],
            'cableType' => ['required', Rule::enum(CableType::class)],
            'cableColour' => ['nullable', 'string', 'max:32'],
            'cableLength' => ['nullable', 'numeric', 'gt:0', 'max:99999'],
            'cableStatus' => ['required', Rule::enum(CableStatus::class)],
            'cableNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        $details = [
            'label' => self::blankToNull($this->cableLabel),
            'type' => CableType::from($this->cableType),
            'colour' => self::blankToNull($this->cableColour),
            'length_m' => self::blankToNull($this->cableLength),
            'status' => CableStatus::from($this->cableStatus),
            'notes' => self::blankToNull($this->cableNotes),
        ];

        if ($this->editingCableId !== null) {
            $connect->update(Cable::query()->findOrFail($this->editingCableId), $details);
        } else {
            $connect->connect($port, Port::query()->findOrFail($this->otherPortId), $details);
        }

        $this->cancelCable();
        unset($this->equipment);
    }

    public function disconnect(string $portId, ConnectPorts $connect): void
    {
        $this->authorizeManage();
        $connect->disconnect($this->port($portId)->cableEnd->cable ?? abort(404));
        $this->cancelCable();
        unset($this->equipment);
    }

    public function trace(string $portId): void
    {
        $this->tracingPortId = $this->tracingPortId === $portId ? null : $this->port($portId)->id;
    }

    public function render(PhysicalAssets $assets): View
    {
        $equipment = $this->equipment;
        $path = $this->tracingPortId !== null ? CablePath::from($this->port($this->tracingPortId)) : [];
        $otherMount = $this->otherMountId !== '' ? Mount::query()->with('ports.cableEnd')->find($this->otherMountId) : null;
        $search = mb_strtolower(trim($this->equipmentSearch));
        $candidates = [];

        $assetIds = [$equipment->asset_id];

        foreach ($equipment->ports as $port) {
            foreach ($port->cableEnd?->cable->ends ?? [] as $end) {
                $assetIds[] = $end->port->mount->asset_id;
            }
        }

        foreach ($path as $step) {
            $assetIds[] = $step['port']->mount->asset_id;
        }

        if (mb_strlen($search) >= 2) {
            $mounts = Mount::query()->with('rack')->get();
            $found = $assets->find(array_values(array_unique($mounts->map(fn (Mount $mount): string => $mount->asset_id)->all())));
            $candidates = $mounts->filter(fn (Mount $mount): bool => isset($found[$mount->asset_id])
                && (str_contains(mb_strtolower($found[$mount->asset_id]->label()), $search) || str_contains(mb_strtolower($mount->rack->name), $search)))
                ->take(10)
                ->map(fn (Mount $mount): array => ['mount' => $mount, 'asset' => $found[$mount->asset_id]])
                ->values()
                ->all();
        }

        if ($otherMount !== null) {
            $assetIds[] = $otherMount->asset_id;
        }

        $labels = $assets->find(array_values(array_unique($assetIds)));

        return view('dcim::livewire.equipment', [
            'equipment' => $equipment,
            'asset' => $labels[$equipment->asset_id] ?? null,
            'labels' => $labels,
            'path' => $path,
            'candidates' => $candidates,
            'otherMount' => $otherMount,
            'canManage' => $this->actor()->can('update', $equipment->rack),
            'kinds' => PortKind::cases(),
            'sides' => MountFace::cases(),
            'cableTypes' => CableType::cases(),
            'cableStatuses' => CableStatus::cases(),
        ])->title(($labels[$equipment->asset_id] ?? null)?->label() ?? __('dcim::cabling.equipment'));
    }

    private function port(string $portId): Port
    {
        return Port::query()->where('mount_id', $this->mountId)->findOrFail($portId);
    }

    private function authorizeManage(): void
    {
        $this->authorize('update', $this->equipment->rack);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private static function blankToNull(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
