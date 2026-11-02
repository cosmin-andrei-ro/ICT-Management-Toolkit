<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Sso;

/**
 * The addresses a host name resolves to, for the outbound URL guard.
 */
interface HostResolver
{
    /**
     * @return list<string> IP addresses; empty when the host is unknown.
     */
    public function resolve(string $host): array;
}
