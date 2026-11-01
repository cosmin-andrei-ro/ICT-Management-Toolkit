<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Ticketing\Application\Email\MailboxUnavailable;
use Modules\Ticketing\Application\Email\MailServerGuard;
use Modules\Ticketing\Domain\Email\MailEncryption;

beforeEach(function (): void {
    config(['ticketing.email.allowed_ports' => [993, 143], 'ticketing.email.allow_unencrypted' => false]);
});

function guardReason(string $host, int $port = 993, MailEncryption $encryption = MailEncryption::Ssl): ?string
{
    try {
        app(MailServerGuard::class)->assertAllowed($host, $port, $encryption);

        return null;
    } catch (MailboxUnavailable $exception) {
        return $exception->reason;
    }
}

it('allows servers on private networks, where on-premises Exchange lives', function (): void {
    expect(guardReason('10.0.0.25'))->toBeNull()
        ->and(guardReason('192.168.1.10', 143, MailEncryption::StartTls))->toBeNull();
});

it('refuses loopback, link-local (cloud metadata), unspecified and multicast addresses', function (string $host): void {
    expect(guardReason($host))->toBe('host_not_allowed');
})->with(['127.0.0.1', '169.254.169.254', '0.0.0.0', '224.0.0.1', '::1', '[::1]', 'localhost']);

it('refuses other ports, unencrypted connections and malformed names', function (): void {
    expect(guardReason('10.0.0.25', 25))->toBe('port_not_allowed')
        ->and(guardReason('10.0.0.25', 143, MailEncryption::None))->toBe('unencrypted')
        ->and(guardReason('mail server;rm -rf'))->toBe('host_not_found');
});

it('allows unencrypted connections only when configured, for development', function (): void {
    config(['ticketing.email.allow_unencrypted' => true]);

    expect(guardReason('10.0.0.25', 143, MailEncryption::None))->toBeNull();
});
