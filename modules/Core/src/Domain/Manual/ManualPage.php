<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Manual;

use Illuminate\Support\Str;

final readonly class ManualPage
{
    public function __construct(
        public string $slug,
        public string $title,
        public ManualAudience $audience,
        public int $order,
        public string $markdown,
    ) {}

    /**
     * Safe HTML: raw HTML in the Markdown is stripped and unsafe link schemes
     * (javascript:, data:) are dropped, so the output may be echoed unescaped.
     */
    public function html(): string
    {
        return Str::markdown($this->markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
