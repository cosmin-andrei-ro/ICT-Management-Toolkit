<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Email;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\Ticketing\Domain\Email\MailEncryption;

/**
 * Mail server settings come from administrators but still decide where the
 * application connects (SSRF): only allowed ports, never loopback, link-local
 * (cloud metadata) or reserved addresses, and encryption unless explicitly allowed.
 * Private networks stay allowed: on-premises Exchange lives there.
 */
final readonly class MailServerGuard
{
    public function __construct(private Config $config) {}

    /**
     * @throws MailboxUnavailable
     */
    public function assertAllowed(string $host, int $port, MailEncryption $encryption): void
    {
        if ($encryption === MailEncryption::None && $this->config->get('ticketing.email.allow_unencrypted') !== true) {
            throw MailboxUnavailable::because('unencrypted');
        }

        if (! in_array($port, $this->allowedPorts(), true)) {
            throw MailboxUnavailable::because('port_not_allowed');
        }

        $addresses = $this->resolve($host);

        if ($addresses === []) {
            throw MailboxUnavailable::because('host_not_found');
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE) === false || $this->isMulticast($address)) {
                throw MailboxUnavailable::because('host_not_allowed');
            }
        }
    }

    /**
     * @return list<int>
     */
    public function allowedPorts(): array
    {
        $ports = $this->config->get('ticketing.email.allowed_ports', []);

        $allowed = [];

        foreach (is_array($ports) ? $ports : [] as $port) {
            $port = filter_var($port, FILTER_VALIDATE_INT);

            if (is_int($port) && $port > 0 && $port <= 65535) {
                $allowed[] = $port;
            }
        }

        return $allowed;
    }

    /**
     * @return list<string>
     */
    private function resolve(string $host): array
    {
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        if (preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/i', $host) !== 1) {
            return [];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        $addresses = [];

        foreach (is_array($records) ? $records : [] as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        // Hosts from /etc/hosts (e.g. Docker service names) are not in DNS.
        if ($addresses === []) {
            $fromResolver = gethostbynamel($host);
            $addresses = $fromResolver !== false ? $fromResolver : [];
        }

        return array_values(array_unique($addresses));
    }

    private function isMulticast(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return ((int) explode('.', $address)[0]) >= 224;
        }

        return str_starts_with(strtolower($address), 'ff');
    }
}
