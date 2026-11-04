<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Sso;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * Identity provider addresses come from administrators but decide where the
 * application connects (SSRF): HTTPS only (plain HTTP only when explicitly
 * allowed, for a development identity provider), never loopback, link-local
 * (cloud metadata) or reserved addresses. Private networks stay allowed: an
 * on-premises identity provider lives there.
 */
final readonly class OutboundUrlGuard
{
    public function __construct(
        private Config $config,
        private HostResolver $resolver,
    ) {}

    public function allows(string $url): bool
    {
        return $this->target($url) !== null;
    }

    /**
     * @return ?array{host: string, port: int, address: string} What to connect to; null when refused.
     */
    public function target(string $url): ?array
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);

        if ($scheme !== 'https' && ! ($scheme === 'http' && $this->config->get('core.sso.allow_http') === true)) {
            return null;
        }

        $addresses = $this->resolver->resolve($parts['host']);

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE) === false) {
                return null;
            }
        }

        return $addresses === [] ? null : [
            'host' => $parts['host'],
            'port' => $parts['port'] ?? ($scheme === 'https' ? 443 : 80),
            'address' => $addresses[0],
        ];
    }
}
