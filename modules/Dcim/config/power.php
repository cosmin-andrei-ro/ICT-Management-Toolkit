<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [
    // A UPS or PDU loaded at or above this share of its capacity needs attention.
    'warn_percent' => (int) env('DCIM_POWER_WARN_PERCENT', 80),
];
