<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

/*
| BIA reviews (config('bia.reviews')).
*/

return [

    // Days before a process's review date when its owner and deputy get an email.
    'reminder_days' => (int) env('BIA_REVIEW_REMINDER_DAYS', 30),

];
