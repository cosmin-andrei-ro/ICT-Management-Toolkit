<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

/*
| Compliance (config('audit.compliance')).
*/

return [

    // Rows per page in the control register.
    'per_page' => 25,

    // Days before a control's next test when its owner is emailed.
    'test_reminder_days' => (int) env('AUDIT_TEST_REMINDER_DAYS', 14),

];
