<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Enums;

enum LocationKind: string
{
    case Site = 'site';
    case Building = 'building';
    case Floor = 'floor';
    case Room = 'room';
    case Other = 'other';

    public function label(): string
    {
        return __('core::locations.kinds.'.$this->value);
    }
}
