<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Application;

use Modules\Dcim\Domain\Models\Cable;
use Modules\Dcim\Domain\Models\Port;

/**
 * Follows a connection from a port: along its cable to the far port, through
 * a patch panel from front to rear (or back), along the next cable, until a
 * port without a cable or a pass-through. A loop or a very long path stops
 * the walk.
 */
final readonly class CablePath
{
    private const int MAX_HOPS = 64;

    /**
     * @return list<array{port: Port, via: ?Cable, throughPanel: bool}> Each port with how it was reached: by a cable, through a panel, or the start.
     */
    public static function from(Port $start): array
    {
        $steps = [['port' => $start, 'via' => null, 'throughPanel' => false]];
        $visited = [$start->id => true];
        $port = $start;

        for ($hop = 0; $hop < self::MAX_HOPS; $hop++) {
            $cable = $port->cableEnd?->cable;
            $far = $cable?->farEnd($port);

            if ($cable === null || $far === null || isset($visited[$far->id])) {
                break;
            }

            $steps[] = ['port' => $far, 'via' => $cable, 'throughPanel' => false];
            $visited[$far->id] = true;

            $next = $far->passesTo();

            if ($next === null || isset($visited[$next->id])) {
                break;
            }

            $steps[] = ['port' => $next, 'via' => null, 'throughPanel' => true];
            $visited[$next->id] = true;
            $port = $next;
        }

        return $steps;
    }
}
