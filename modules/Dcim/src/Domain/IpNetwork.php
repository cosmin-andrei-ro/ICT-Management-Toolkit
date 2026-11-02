<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Domain;

/**
 * An IPv4 or IPv6 network ("10.0.0.0/24", "2001:db8::/48") read from what
 * people type: canonical text, and whether host bits were set
 * ("10.0.0.5/24" is the network 10.0.0.0/24).
 */
final readonly class IpNetwork
{
    private function __construct(
        public string $network,
        public int $prefix,
        public int $family,
        public bool $hadHostBits,
    ) {}

    public static function parse(string $value): ?self
    {
        $parts = explode('/', trim($value));

        if (count($parts) !== 2 || preg_match('/^\d{1,3}$/', $parts[1]) !== 1) {
            return null;
        }

        $packed = @inet_pton($parts[0]);

        if ($packed === false) {
            return null;
        }

        $bits = strlen($packed) * 8;
        $prefix = (int) $parts[1];

        if ($prefix > $bits) {
            return null;
        }

        $masked = '';

        for ($byte = 0; $byte < strlen($packed); $byte++) {
            $keep = max(0, min(8, $prefix - $byte * 8));
            $masked .= chr(ord($packed[$byte]) & (0xFF << (8 - $keep)) & 0xFF);
        }

        return new self((string) inet_ntop($masked), $prefix, $bits === 32 ? 4 : 6, $masked !== $packed);
    }

    /**
     * A single address in canonical form ("2001:DB8::0001" → "2001:db8::1"), or null.
     */
    public static function address(string $value): ?string
    {
        $packed = @inet_pton(trim($value));

        return $packed === false ? null : (string) inet_ntop($packed);
    }

    public function cidr(): string
    {
        return $this->network.'/'.$this->prefix;
    }

    /**
     * Addresses that can be given to hosts: an IPv4 network loses its network
     * and broadcast addresses (except /31 and /32); null for IPv6, where
     * counting is meaningless.
     */
    public function usable(): ?int
    {
        if ($this->family === 6) {
            return null;
        }

        $size = 2 ** (32 - $this->prefix);

        return $this->prefix >= 31 ? $size : $size - 2;
    }
}
