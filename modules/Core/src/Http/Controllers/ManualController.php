<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Contracts\View\View;
use Modules\Core\Domain\Manual\Manual;

/**
 * Public on purpose (owner's decision): the manual holds no organization data, and
 * people who cannot sign in need it most.
 */
final readonly class ManualController
{
    public function __construct(private Manual $manual) {}

    public function index(): View
    {
        return view('core::manual.index', ['contents' => $this->manual->contents()]);
    }

    public function show(string $page): View
    {
        $current = $this->manual->find($page);
        abort_if($current === null, 404);

        $pages = array_values($this->manual->pages());
        $position = array_search($current, $pages, true);

        return view('core::manual.show', [
            'contents' => $this->manual->contents(),
            'page' => $current,
            'previous' => is_int($position) ? ($pages[$position - 1] ?? null) : null,
            'next' => is_int($position) ? ($pages[$position + 1] ?? null) : null,
        ]);
    }
}
