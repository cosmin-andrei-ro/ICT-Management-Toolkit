<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [

    // Reverse proxies (load balancer, TLS terminator) whose X-Forwarded-* headers
    // are believed: comma-separated addresses or CIDR ranges, e.g. 10.0.0.5,10.1.0.0/16.
    // Empty: none. Never "*" on a server reachable without the proxy: anyone could
    // then fake their IP address (and dodge rate limits) or the HTTPS scheme.
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),

];
