<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Tests\Support;

use Closure;
use Modules\Ticketing\Application\Email\InboundMessage;
use Modules\Ticketing\Application\Email\MailboxReader;
use Modules\Ticketing\Application\Email\MailboxUnavailable;
use Modules\Ticketing\Domain\Models\Mailbox;

final class FakeMailboxReader implements MailboxReader
{
    /** @var list<int> */
    public array $processed = [];

    /**
     * @param  list<InboundMessage>  $messages
     */
    public function __construct(
        public array $messages = [],
        public ?string $failure = null,
    ) {}

    public function read(Mailbox $mailbox, int $limit, Closure $handler): int
    {
        $this->test($mailbox);
        $batch = array_slice($this->messages, 0, $limit);

        foreach ($batch as $message) {
            $handler($message);
            $this->processed[] = $message->uid;
        }

        $this->messages = array_slice($this->messages, $limit);

        return count($batch);
    }

    public function test(Mailbox $mailbox): void
    {
        if ($this->failure !== null) {
            throw MailboxUnavailable::because($this->failure);
        }
    }
}
