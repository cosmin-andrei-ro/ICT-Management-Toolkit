<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Domain\Enums;

enum DisposalMethod: string
{
    case Sold = 'sold';
    case Donated = 'donated';
    case Recycled = 'recycled';
    case Destroyed = 'destroyed';
    /** Given back to the lessor at the end of a lease. */
    case Returned = 'returned';

    public function label(): string
    {
        return __('itam::assets.disposal_methods.'.$this->value);
    }
}
