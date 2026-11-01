<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Email;

use Closure;
use Modules\Ticketing\Domain\Models\Mailbox;

interface MailboxReader
{
    /**
     * Hands up to $limit unread messages, oldest first, to $handler, then marks
     * each as processed (read, and moved when the mailbox says so).
     *
     * @param  Closure(InboundMessage): void  $handler
     * @return int Messages handed over.
     *
     * @throws MailboxUnavailable
     */
    public function read(Mailbox $mailbox, int $limit, Closure $handler): int;

    /**
     * Connects, signs in and opens the folders, without reading anything.
     *
     * @throws MailboxUnavailable
     */
    public function test(Mailbox $mailbox): void;
}
