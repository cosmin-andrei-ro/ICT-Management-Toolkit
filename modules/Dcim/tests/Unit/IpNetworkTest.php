<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Dcim\Domain\IpNetwork;

it('reads networks, telling when host bits were set', function (): void {
    $network = IpNetwork::parse(' 10.20.0.0/24 ');
    expect($network?->cidr())->toBe('10.20.0.0/24')->and($network?->hadHostBits)->toBeFalse()->and($network?->family)->toBe(4);

    $typed = IpNetwork::parse('10.20.0.5/24');
    expect($typed?->cidr())->toBe('10.20.0.0/24')->and($typed?->hadHostBits)->toBeTrue();

    expect(IpNetwork::parse('2001:DB8:10::1/64')?->cidr())->toBe('2001:db8:10::/64')
        ->and(IpNetwork::parse('10.20.0.0'))->toBeNull()
        ->and(IpNetwork::parse('10.20.0.0/33'))->toBeNull()
        ->and(IpNetwork::parse('nonsense/24'))->toBeNull();
});

it('counts usable IPv4 addresses and writes addresses canonically', function (): void {
    expect(IpNetwork::parse('10.0.0.0/24')?->usable())->toBe(254)
        ->and(IpNetwork::parse('10.0.0.0/31')?->usable())->toBe(2)
        ->and(IpNetwork::parse('10.0.0.1/32')?->usable())->toBe(1)
        ->and(IpNetwork::parse('2001:db8::/64')?->usable())->toBeNull()
        ->and(IpNetwork::address('2001:DB8::0001'))->toBe('2001:db8::1')
        ->and(IpNetwork::address('10.0.0.256'))->toBeNull();
});
