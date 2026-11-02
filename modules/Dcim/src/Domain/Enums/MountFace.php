<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Domain\Enums;

/**
 * The side of the rack equipment is mounted from.
 */
enum MountFace: string
{
    case Front = 'front';
    case Rear = 'rear';

    public function label(): string
    {
        return __('dcim::racks.faces.'.$this->value);
    }
}
