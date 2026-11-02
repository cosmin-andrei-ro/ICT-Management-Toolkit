<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Dcim\Domain\Enums\MountFace;
use Modules\Dcim\Domain\Enums\PortKind;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\Port;

/**
 * Adds ports to mounted equipment, one or a series ("Gi1/0/[1-48]"). For a
 * patch panel each front port gets its rear port, wired to it.
 */
final readonly class AddPorts
{
    public const int MAX = 256;

    /**
     * @return list<string>
     */
    public static function names(string $pattern): array
    {
        $pattern = trim($pattern);

        if (preg_match('/^(.*)\[(\d{1,4})-(\d{1,4})\](.*)$/', $pattern, $match) !== 1) {
            return $pattern === '' ? [] : [$pattern];
        }

        [, $prefix, $from, $to, $suffix] = $match;

        if ((int) $from > (int) $to || (int) $to - (int) $from + 1 > self::MAX) {
            return [];
        }

        // "[01-24]" keeps its leading zeros.
        $width = str_starts_with($from, '0') ? strlen($from) : 0;

        return array_map(fn (int $number): string => $prefix.str_pad((string) $number, $width, '0', STR_PAD_LEFT).$suffix, range((int) $from, (int) $to));
    }

    /**
     * @return int Ports added.
     */
    public function handle(Mount $mount, string $pattern, PortKind $kind, MountFace $side, bool $patchPanel): int
    {
        $names = self::names($pattern);

        if ($names === []) {
            throw ValidationException::withMessages(['pattern' => __('dcim::cabling.errors.pattern', ['max' => self::MAX])]);
        }

        $wanted = $patchPanel ? [...$names, ...array_map(fn (string $name): string => __('dcim::cabling.rear_name', ['name' => $name]), $names)] : $names;
        $existing = $mount->ports()->get();
        $taken = $existing->map(fn (Port $port): string => mb_strtolower($port->name))->all();
        $clash = array_values(array_filter($wanted, fn (string $name): bool => in_array(mb_strtolower($name), $taken, true) || mb_strlen($name) > 64));

        if ($clash !== []) {
            throw ValidationException::withMessages(['pattern' => __('dcim::cabling.errors.names_taken', ['names' => implode(', ', array_slice($clash, 0, 5))])]);
        }

        $position = (int) $existing->max(fn (Port $port): int => $port->position);

        DB::transaction(function () use ($mount, $names, $kind, $side, $patchPanel, &$position): void {
            foreach ($names as $name) {
                $rear = null;

                if ($patchPanel) {
                    $rear = $this->create($mount, __('dcim::cabling.rear_name', ['name' => $name]), $kind, MountFace::Rear, ++$position, null);
                }

                $this->create($mount, $name, $kind, $patchPanel ? MountFace::Front : $side, ++$position, $rear?->id);
            }
        });

        return count($names) * ($patchPanel ? 2 : 1);
    }

    private function create(Mount $mount, string $name, PortKind $kind, MountFace $side, int $position, ?string $rearPortId): Port
    {
        $port = new Port;
        $port->forceFill(['mount_id' => $mount->id, 'name' => $name, 'kind' => $kind, 'side' => $side, 'position' => $position, 'rear_port_id' => $rearPortId])->save();

        return $port;
    }
}
