<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Active Directory (LDAP) authentication — config('core.directory')
|--------------------------------------------------------------------------
|
| Connection settings and the service account stay in the environment so the
| secret is never stored in the database. Group-to-role mappings are managed
| in the admin panel. See docs/adr/0007-active-directory-authentication.md.
|
*/

return [

    'enabled' => filter_var(env('LDAP_ENABLED', false), FILTER_VALIDATE_BOOL),

    // Comma-separated domain controllers, tried in order.
    'hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('LDAP_HOSTS', ''))))),

    'port' => (int) env('LDAP_PORT', 636),

    'base_dn' => (string) env('LDAP_BASE_DN', ''),

    // Service account used to look users up; its password is never logged.
    'username' => (string) env('LDAP_USERNAME', ''),
    'password' => (string) env('LDAP_PASSWORD', ''),

    // "ldaps" (port 636), "starttls" (port 389, upgraded) or "none".
    'encryption' => (string) env('LDAP_ENCRYPTION', 'ldaps'),

    // Plain-text LDAP sends passwords in clear; refused unless explicitly allowed.
    'allow_insecure' => filter_var(env('LDAP_ALLOW_INSECURE', false), FILTER_VALIDATE_BOOL),

    // CA bundle used to verify the domain controllers' certificates.
    'ca_cert' => env('LDAP_CA_CERT') !== null ? (string) env('LDAP_CA_CERT') : null,

    'timeout' => (int) env('LDAP_TIMEOUT', 5),

    // How often core:directory:sync re-checks accounts (cron expression). Accounts
    // disabled in AD keep an open session at most this long.
    'sync_schedule' => (string) env('LDAP_SYNC_SCHEDULE', '*/15 * * * *'),

];
