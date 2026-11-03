<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Webhooks;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\Core\Application\Sso\HostResolver;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Where a webhook may be sent (SSRF, CLAUDE.md §6): HTTPS only, never
 * loopback, link-local (cloud metadata) or reserved addresses, and no private
 * network unless the installation allows it. The address is checked again at
 * every delivery and the connection is pinned to it, so a DNS answer that
 * changes in between (rebinding) cannot reach an internal host.
 */
final readonly class WebhookUrlGuard
{
    /** Internal too, though PHP's private flag misses them: carrier-grade NAT (some clouds' metadata) and NAT64. */
    private const array OTHER_PRIVATE_RANGES = [
        '100.64.0.0/10',    // carrier-grade NAT
        '192.0.0.0/24',     // IETF protocol assignments
        '198.18.0.0/15',    // benchmarking
        '224.0.0.0/4',      // multicast
        '64:ff9b::/96',     // NAT64
        '64:ff9b:1::/48',
        '::ffff:0:0:0/96',  // IPv4-translated
        '2002::/16',        // 6to4: wraps any IPv4 address, internal ones included
        'fec0::/10',        // old site-local
        'ff00::/8',         // multicast
    ];

    public function __construct(private Config $config, private HostResolver $resolver) {}

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

        if ($scheme !== 'https' && ! ($scheme === 'http' && $this->config->get('core.webhooks.allow_http') === true)) {
            return null;
        }

        $host = trim($parts['host'], '[]');
        $allowPrivate = $this->config->get('core.webhooks.allow_private_networks') === true;
        $flags = FILTER_FLAG_NO_RES_RANGE | ($allowPrivate ? 0 : FILTER_FLAG_NO_PRIV_RANGE);
        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $this->resolver->resolve($host);

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, $flags) === false || (! $allowPrivate && IpUtils::checkIp($address, self::OTHER_PRIVATE_RANGES))) {
                return null;
            }
        }

        return $addresses === [] ? null : [
            'host' => $host,
            'port' => isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80),
            'address' => $addresses[0],
        ];
    }

    public function allows(string $url): bool
    {
        return $this->target($url) !== null;
    }
}
