<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Sso;

final readonly class DnsHostResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
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
}
