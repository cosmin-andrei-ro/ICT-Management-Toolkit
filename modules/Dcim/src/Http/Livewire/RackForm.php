<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Http\Livewire;

use Closure;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Core\Domain\Models\Location;
use Modules\Dcim\Application\Actions\SaveRack;
use Modules\Dcim\Domain\Models\Rack;

#[Layout('core::components.layouts.app')]
final class RackForm extends Component
{
    #[Locked]
    public ?string $rackId = null;

    #[Url(as: 'location')]
    public string $locationId = '';

    public string $name = '';

    public string $row = '';

    public string $position = '';

    public string $heightU = '42';

    public string $maxPowerWatts = '';

    public string $notes = '';

    public function mount(?Rack $rack = null): void
    {
        if ($rack === null || ! $rack->exists) {
            $this->authorize('create', Rack::class);

            return;
        }

        $this->authorize('update', $rack);
        $this->rackId = $rack->id;
        $this->locationId = $rack->location_id;
        $this->name = $rack->name;
        $this->row = $rack->row ?? '';
        $this->position = (string) ($rack->position ?? '');
        $this->heightU = (string) $rack->height_u;
        $this->maxPowerWatts = (string) ($rack->max_power_watts ?? '');
        $this->notes = $rack->notes ?? '';
    }

    public function save(SaveRack $save): void
    {
        $rack = $this->rackId !== null ? Rack::query()->findOrFail($this->rackId) : null;
        $this->authorize($rack !== null ? 'update' : 'create', $rack ?? Rack::class);
        $this->resetErrorBag();

        $this->validate([
            'locationId' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:64', function (string $attribute, mixed $value, Closure $fail): void {
                $taken = is_string($value) && Rack::query()->where('location_id', $this->locationId)
                    ->whereRaw('lower(name) = ?', [mb_strtolower(trim($value))])
                    ->when($this->rackId !== null, fn ($query) => $query->whereKeyNot($this->rackId))
                    ->exists();

                if ($taken) {
                    $fail(__('dcim::racks.errors.name_taken'));
                }
            }],
            'row' => ['nullable', 'string', 'max:32'],
            'position' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'heightU' => ['required', 'integer', 'min:1', 'max:60'],
            'maxPowerWatts' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $rack = $save->handle($rack, [
            'location_id' => $this->locationId,
            'name' => trim($this->name),
            'row' => trim($this->row) !== '' ? trim($this->row) : null,
            'position' => $this->position !== '' ? (int) $this->position : null,
            'height_u' => (int) $this->heightU,
            'max_power_watts' => $this->maxPowerWatts !== '' ? (int) $this->maxPowerWatts : null,
            'notes' => trim($this->notes) !== '' ? trim($this->notes) : null,
        ]);

        $this->redirectRoute('dcim.racks.show', $rack);
    }

    public function render(): View
    {
        return view('dcim::livewire.rack-form', ['locations' => Location::paths()])
            ->title(__($this->rackId !== null ? 'dcim::racks.edit' : 'dcim::racks.new'));
    }
}
