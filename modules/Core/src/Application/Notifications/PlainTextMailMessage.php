<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Notifications;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * A notification e-mail whose lines are plain text. Laravel renders lines as
 * Markdown, so a ticket title such as "[Sign in again](https://evil.example)"
 * became a real link in an e-mail sent by the toolkit. None of our texts use
 * Markdown, so the characters that build links, images and emphasis are
 * escaped in every line.
 */
final class PlainTextMailMessage extends MailMessage
{
    /**
     * @param  Htmlable|string|array<int, string>|null  $line
     */
    protected function formatLine($line): Htmlable|string
    {
        $formatted = parent::formatLine($line);

        if ($formatted instanceof Htmlable) {
            return $formatted;
        }

        return (string) preg_replace('/^#/', '\\#', addcslashes($formatted, '\\[]*_`'));
    }
}
