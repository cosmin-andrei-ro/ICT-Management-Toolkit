<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

/**
 * A ticket as other modules show it.
 */
final readonly class TicketSummary
{
    public function __construct(
        public string $id,
        public string $number,
        public string $title,
        public string $statusLabel,
        public bool $isOpen,
        public string $url,
    ) {}
}
