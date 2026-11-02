<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    // Days of daily values kept: two years, so a year can be compared with the previous one.
    'keep_days' => (int) env('REPORTING_SNAPSHOT_KEEP_DAYS', 730),
];
