<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

/**
 * A configuration item with what IT can deliver when it fails, as the business
 * impact analysis compares it with what the business requires.
 */
final readonly class RecoveryProfile
{
    public function __construct(
        public string $id,
        public string $name,
        public string $typeName,
        public string $url,
        /** low, medium, high or critical. */
        public string $criticality,
        public ?int $recoveryTimeMinutes,
        public ?int $recoveryPointMinutes,
        /** Y-m-d of the last recovery test that brought it back. */
        public ?string $verifiedOn = null,
        public ?int $verifiedMinutes = null,
    ) {}
}
