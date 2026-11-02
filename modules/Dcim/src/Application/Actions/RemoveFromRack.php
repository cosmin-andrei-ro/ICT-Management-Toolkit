<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Application\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Dcim\Domain\Models\Mount;
use Modules\Dcim\Domain\Models\Rack;

/**
 * Takes equipment out of a rack, or removes an empty rack.
 */
final readonly class RemoveFromRack
{
    public function unmount(Mount $mount): void
    {
        $mount->delete();
    }

    public function deleteRack(Rack $rack): void
    {
        if ($rack->mounts()->exists()) {
            throw ValidationException::withMessages(['rack' => __('dcim::racks.errors.not_empty')]);
        }

        $rack->delete();
    }
}
