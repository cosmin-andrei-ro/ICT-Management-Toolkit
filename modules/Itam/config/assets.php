<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

/*
| Asset management (config('itam.assets')).
*/

return [

    // Asset tags handed out automatically: PREFIX-000123.
    'tag_prefix' => strtoupper((string) env('ITAM_TAG_PREFIX', 'AST')),

    // Days before a warranty ends when asset managers get an email.
    'warranty_reminder_days' => (int) env('ITAM_WARRANTY_REMINDER_DAYS', 30),

];
