<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Email;

use Carbon\CarbonImmutable;

/**
 * A message read from a mailbox, independent of the IMAP library. The body is
 * already plain text (HTML converted), but not yet stripped of quoted replies.
 */
final readonly class InboundMessage
{
    /**
     * @param  array<string, list<string>>  $headers  Lowercase header name => values, for the headers intake looks at.
     * @param  list<InboundAttachment>  $attachments
     */
    public function __construct(
        public int $uid,
        public ?string $messageId,
        public string $fromAddress,
        public string $subject,
        public string $text,
        public ?CarbonImmutable $receivedAt = null,
        public int $size = 0,
        public array $headers = [],
        public array $attachments = [],
    ) {}

    /**
     * Identifies the message across runs, so it is handled once.
     */
    public function key(): string
    {
        $id = $this->messageId !== null && trim($this->messageId) !== ''
            ? 'id:'.trim($this->messageId)
            : 'raw:'.$this->fromAddress.'|'.$this->receivedAt?->toIso8601String().'|'.$this->subject.'|'.hash('sha256', $this->text);

        return hash('sha256', $id);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }
}
