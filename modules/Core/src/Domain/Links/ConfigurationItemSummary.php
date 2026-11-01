<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

/**
 * A configuration item as other modules see it, without depending on the CMDB.
 */
final readonly class ConfigurationItemSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public string $typeName,
        public string $criticality,
        public string $criticalityLabel,
        public string $url,
        /** Steps away from the linked items, for impact lists; 0 otherwise. */
        public int $depth = 0,
    ) {}
}
