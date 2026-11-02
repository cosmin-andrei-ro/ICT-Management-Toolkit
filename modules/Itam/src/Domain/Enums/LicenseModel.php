<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Domain\Enums;

/**
 * How a licence is counted: seats for people, seats for devices, or a site
 * licence without a count (who uses it can still be recorded).
 */
enum LicenseModel: string
{
    case PerUser = 'per_user';
    case PerDevice = 'per_device';
    case Site = 'site';

    public function label(): string
    {
        return __('itam::licenses.models.'.$this->value);
    }

    public function isCounted(): bool
    {
        return $this !== self::Site;
    }

    public function forDevices(): bool
    {
        return $this === self::PerDevice;
    }
}
