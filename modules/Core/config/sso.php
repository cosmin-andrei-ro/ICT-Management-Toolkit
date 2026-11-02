<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

/*
| Single sign-on (config('core.sso'), ADR 0038). Identity providers are set
| up per organization in Administration → Access → Single sign-on.
*/

return [

    // Plain HTTP identity providers, for a development Keycloak only. Never in production.
    'allow_http' => (bool) env('SSO_ALLOW_HTTP', false),

];
