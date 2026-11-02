<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

/**
 * A business process as other modules see it, without depending on the BIA.
 */
final readonly class ProcessSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public string $url,
        /** From the approved assessment; null when never approved. */
        public ?int $rtoMinutes,
        public ?int $rpoMinutes,
        /** 1–4; null when never approved. */
        public ?int $tier,
    ) {}
}
