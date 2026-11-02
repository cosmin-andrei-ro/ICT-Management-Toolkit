<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Text;

use Illuminate\Support\Str;

/**
 * Markdown written by people, turned into HTML that may be echoed unescaped
 * (CLAUDE.md §6): GitHub-flavoured (tables, task lists), raw HTML stripped,
 * unsafe link schemes (javascript:, data:) dropped.
 */
final class Markdown
{
    public static function toSafeHtml(string $markdown): string
    {
        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
