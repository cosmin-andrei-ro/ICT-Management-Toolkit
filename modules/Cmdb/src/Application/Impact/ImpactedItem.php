<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Application\Impact;

use Modules\Cmdb\Domain\Models\ConfigurationItem;

final readonly class ImpactedItem
{
    public function __construct(
        public ConfigurationItem $item,
        /** 1 = directly related; 2 = through one other item… */
        public int $depth,
    ) {}
}
