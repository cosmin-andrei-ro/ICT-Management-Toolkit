<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

/*
| PDF documents (config('core.pdf'), ADR 0030): rendered by an optional
| Gotenberg container (headless Chromium). Without a URL the toolkit offers
| the browser's print / save as PDF instead.
*/

return [

    // e.g. http://gotenberg:3000 - internal network only, never exposed.
    'gotenberg_url' => (string) env('GOTENBERG_URL', ''),

    'timeout_seconds' => (int) env('GOTENBERG_TIMEOUT', 60),

];
