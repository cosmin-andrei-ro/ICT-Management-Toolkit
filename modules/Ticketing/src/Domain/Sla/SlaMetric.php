<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Sla;

enum SlaMetric: string
{
    case Response = 'response';
    case Resolution = 'resolution';

    public function label(): string
    {
        return __('ticketing::sla.metrics.'.$this->value);
    }
}
