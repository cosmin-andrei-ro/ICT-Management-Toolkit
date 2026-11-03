<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [

    // Consecutive failed sign-ins (password or second factor) after which a local
    // account is locked until a password reset or an administrator unlocks it.
    // 0 turns locking off. Active Directory accounts follow the directory's own policy.
    'lockout_after' => max(0, (int) env('AUTH_LOCKOUT_AFTER', 10)),

    // New passwords are checked against the Have I Been Pwned breach corpus
    // (k-anonymity: only the first 5 characters of the password's SHA-1 leave
    // the server). Turn off for installations without internet access.
    // If the service does not answer, the password is accepted.
    'check_breached_passwords' => (bool) env('AUTH_CHECK_BREACHED_PASSWORDS', true),

    // Seconds to wait for the breach check before accepting the password.
    'breached_passwords_timeout' => max(1, (int) env('AUTH_BREACHED_PASSWORDS_TIMEOUT', 5)),

];
