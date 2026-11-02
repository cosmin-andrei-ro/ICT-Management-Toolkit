<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

/*
| Continuity plans (config('bcm.plans')).
*/

return [

    // After approval, a plan is due for review after this many months.
    'review_months' => (int) env('BCM_REVIEW_MONTHS', 12),

    // A plan in force is due for an exercise this many months after the last one (or its approval).
    'exercise_months' => (int) env('BCM_EXERCISE_MONTHS', 12),

    // Days before a review or an exercise is due when the plan's owner and deputy get an email.
    'reminder_days' => (int) env('BCM_REMINDER_DAYS', 30),

];
