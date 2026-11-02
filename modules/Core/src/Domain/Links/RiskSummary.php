<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

/**
 * A risk of the register as other modules show it.
 */
final readonly class RiskSummary
{
    public function __construct(
        public string $id,
        public string $code,
        public string $title,
        public string $url,
        /** Level of the current risk (residual where assessed), e.g. "High"; null while not scored. */
        public ?string $level,
        public ?int $score,
        public string $status,
    ) {}
}
