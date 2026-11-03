<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

return [

    // Private networks (10/8, 172.16/12, 192.168/16, fc00::/7) are refused by
    // default, like loopback and link-local (cloud metadata) addresses, which
    // are always refused. Allow them for on-premises receivers.
    'allow_private_networks' => (bool) env('WEBHOOKS_ALLOW_PRIVATE_NETWORKS', false),

    // Plain HTTP only for development receivers.
    'allow_http' => (bool) env('WEBHOOKS_ALLOW_HTTP', false),

    // Seconds to wait for the receiver.
    'timeout' => max(1, (int) env('WEBHOOKS_TIMEOUT', 10)),

];
