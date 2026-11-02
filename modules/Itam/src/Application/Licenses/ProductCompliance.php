<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Licenses;

use Illuminate\Support\Collection;
use Modules\Itam\Domain\Models\License;
use Modules\Itam\Domain\Models\SoftwareProduct;

/**
 * Seats bought and used for one product, over its licences valid today.
 */
final readonly class ProductCompliance
{
    /**
     * @param  Collection<int, License>  $licenses  With assignments_count.
     * @param  ?int  $purchased  Null when a valid site licence covers everyone.
     */
    public function __construct(
        public SoftwareProduct $product,
        public Collection $licenses,
        public ?int $purchased,
        public int $used,
    ) {}

    public function isOverAllocated(): bool
    {
        return $this->purchased !== null && $this->used > $this->purchased;
    }
}
