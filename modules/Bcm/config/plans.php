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

];
