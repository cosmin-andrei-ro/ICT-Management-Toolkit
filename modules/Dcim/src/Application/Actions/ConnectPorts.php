<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Application\Actions;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Dcim\Domain\Enums\CableStatus;
use Modules\Dcim\Domain\Enums\CableType;
use Modules\Dcim\Domain\Models\Cable;
use Modules\Dcim\Domain\Models\CableEnd;
use Modules\Dcim\Domain\Models\Port;

/**
 * Lays a cable between two free ports, changes its details, or removes it.
 */
final readonly class ConnectPorts
{
    /**
     * @param  array{label: ?string, type: CableType, colour: ?string, length_m: ?string, status: CableStatus, notes: ?string}  $details
     */
    public function connect(Port $a, Port $b, array $details): Cable
    {
        if ($a->id === $b->id) {
            throw ValidationException::withMessages(['otherPortId' => __('dcim::cabling.errors.same_port')]);
        }

        foreach ([$a, $b] as $port) {
            if (CableEnd::query()->where('port_id', $port->id)->exists()) {
                throw ValidationException::withMessages(['otherPortId' => __('dcim::cabling.errors.port_taken', ['port' => $port->name])]);
            }
        }

        $this->ensureLabelFree($details['label'], null);

        try {
            return DB::transaction(function () use ($a, $b, $details): Cable {
                $cable = new Cable;
                $cable->forceFill($details)->save();

                foreach (['a' => $a, 'b' => $b] as $end => $port) {
                    (new CableEnd)->forceFill(['cable_id' => $cable->id, 'end' => $end, 'port_id' => $port->id])->save();
                }

                return $cable;
            });
        } catch (QueryException $exception) {
            // Someone cabled one of the ports in between: the unique port refused it.
            if ($exception->getCode() === '23505') {
                throw ValidationException::withMessages(['otherPortId' => __('dcim::cabling.errors.taken_meanwhile')]);
            }

            throw $exception;
        }
    }

    /**
     * @param  array{label: ?string, type: CableType, colour: ?string, length_m: ?string, status: CableStatus, notes: ?string}  $details
     */
    public function update(Cable $cable, array $details): void
    {
        $this->ensureLabelFree($details['label'], $cable->id);
        $cable->forceFill($details)->save();
    }

    public function disconnect(Cable $cable): void
    {
        $cable->delete();
    }

    private function ensureLabelFree(?string $label, ?string $exceptId): void
    {
        if ($label === null) {
            return;
        }

        $taken = Cable::query()->whereRaw('lower(label) = ?', [mb_strtolower($label)])
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['cableLabel' => __('dcim::cabling.errors.label_taken')]);
        }
    }
}
