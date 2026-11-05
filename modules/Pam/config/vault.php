<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [

    /*
    | The key that encrypts privileged credentials (ADR 0078). Apart from APP_KEY on
    | purpose: a leaked application key must not open the vault. Make it with
    | "php artisan pam:key". Without it, the vault stores and shows nothing.
    */
    'key' => (string) env('PAM_KEY', ''),

    // Earlier keys, comma separated, while "php artisan pam:rekey" moves credentials to the new one.
    'previous_keys' => array_values(array_filter(array_map('trim', explode(',', (string) env('PAM_PREVIOUS_KEYS', ''))))),

    // How long a shown credential stays on the screen, in seconds.
    'reveal_seconds' => (int) env('PAM_REVEAL_SECONDS', 60),

    // Hours a newly set up authenticator app must wait before it can show credentials. 0: none.
    'second_factor_settle_hours' => (int) env('PAM_SECOND_FACTOR_SETTLE_HOURS', 24),
];
