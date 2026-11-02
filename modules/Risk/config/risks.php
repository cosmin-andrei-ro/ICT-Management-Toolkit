<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

/*
| Risk register (config('risk.risks')). The scoring method is set per
| organization in Administration (RiskSettings).
*/

return [

    // Rows per page in the register.
    'per_page' => 25,

    // Days before a risk's reassessment date when its owner is emailed.
    'reminder_days' => (int) env('RISK_REMINDER_DAYS', 30),

];
