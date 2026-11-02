<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Application;

use Modules\Dcim\Domain\Enums\MountFace;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\Rack;

/**
 * One side of a rack from the top unit down: each piece of equipment as one
 * block over its units, each free unit on its own.
 */
final readonly class Elevation
{
    /**
     * @return list<array{top: int, bottom: int, mount: ?Mount}>
     */
    public static function of(Rack $rack, MountFace $face): array
    {
        $byTop = [];

        foreach ($rack->mounts as $mount) {
            if ($mount->occupies($face)) {
                $byTop[(int) $mount->topUnit()] = $mount;
            }
        }

        $blocks = [];
        $unit = $rack->height_u;

        while ($unit >= 1) {
            $mount = $byTop[$unit] ?? null;

            if ($mount !== null) {
                $blocks[] = ['top' => $unit, 'bottom' => (int) $mount->position_u, 'mount' => $mount];
                $unit = (int) $mount->position_u - 1;

                continue;
            }

            $blocks[] = ['top' => $unit, 'bottom' => $unit, 'mount' => null];
            $unit--;
        }

        return $blocks;
    }
}
