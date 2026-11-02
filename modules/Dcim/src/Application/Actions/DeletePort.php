<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Application\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Dcim\Domain\Models\Port;

/**
 * Removes a port without a cable. Removing a patch panel's front port also
 * removes the rear port wired to it.
 */
final readonly class DeletePort
{
    public function handle(Port $port): void
    {
        $rear = $port->rearPort;

        foreach (array_filter([$port, $rear]) as $removed) {
            if ($removed->cableEnd()->exists()) {
                throw ValidationException::withMessages(['ports' => __('dcim::cabling.errors.port_cabled', ['port' => $removed->name])]);
            }
        }

        $port->delete();
        $rear?->delete();
    }
}
