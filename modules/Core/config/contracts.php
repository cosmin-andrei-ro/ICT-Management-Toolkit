<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

/*
| Contracts (config('core.contracts')). The deadline of a contract is its end date
| minus its notice period: the last day to cancel or renegotiate.
*/

return [

    // Days before the deadline when the owner and contract managers get an email.
    // The largest also marks a contract as "expiring" in the lists.
    'reminder_days' => array_values(array_unique(array_filter(
        array_map('intval', explode(',', (string) env('CONTRACT_REMINDER_DAYS', '90,30'))),
        fn (int $days): bool => $days > 0 && $days <= 365,
    ))),

    // Proposed for new contracts (ISO 4217).
    'default_currency' => strtoupper((string) env('DEFAULT_CURRENCY', 'EUR')),

];
