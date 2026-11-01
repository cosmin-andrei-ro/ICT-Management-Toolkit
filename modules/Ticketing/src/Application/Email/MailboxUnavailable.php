<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Email;

use RuntimeException;
use Throwable;

/**
 * Carries a short reason code only: server messages can echo what was sent,
 * credentials included, so they are never stored or shown.
 */
final class MailboxUnavailable extends RuntimeException
{
    public const array REASONS = ['connection', 'authentication', 'folder', 'host_not_allowed', 'port_not_allowed', 'unencrypted', 'host_not_found'];

    private function __construct(public readonly string $reason, ?Throwable $previous = null)
    {
        parent::__construct('Mailbox unavailable: '.$reason, previous: $previous);
    }

    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self(in_array($reason, self::REASONS, true) ? $reason : 'connection', $previous);
    }
}
