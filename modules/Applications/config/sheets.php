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

    // Days before a certificate of a sheet in force (8.1) expires when the owners are emailed.
    'certificate_reminder_days' => (int) env('APPLICATIONS_CERTIFICATE_REMINDER_DAYS', 30),

];
