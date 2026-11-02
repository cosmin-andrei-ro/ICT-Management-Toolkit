<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    // A status report older than this is due again; the weekly reminder goes to the manager.
    'every_days' => (int) env('PROJECTS_REPORT_EVERY_DAYS', 14),
];
