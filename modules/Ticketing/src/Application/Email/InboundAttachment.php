<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Email;

final readonly class InboundAttachment
{
    public function __construct(
        public string $filename,
        public string $contents,
        /** Shown inline in the message body (signature logos and the like). */
        public bool $inline = false,
    ) {}
}
