<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

/**
 * A record of some module, as a link elsewhere shows it.
 */
final readonly class LinkedRecord
{
    public function __construct(
        public string $id,
        public string $title,
        public string $url,
        public ?string $status = null,
    ) {}
}
