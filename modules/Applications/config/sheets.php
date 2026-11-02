<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

/*
| Application documentation sheets (config('applications.sheets')).
*/

return [

    // A sheet in force is due for review after this many months (the template asks at least once a year).
    'review_months' => (int) env('APPLICATIONS_REVIEW_MONTHS', 12),

];
