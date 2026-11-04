<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Ticketing\Application\Email\EmailBody;

it('turns HTML into plain text without any markup or script', function (): void {
    $text = EmailBody::fromHtml('<html><head><style>p{}</style><script>alert(1)</script></head><body><p>Hello <b>desk</b>,</p><p>VPN is down.<br>Since 9:00.</p><ul><li>Laptop</li><li>Phone</li></ul><img src="http://tracker.example/x.png" onerror="alert(1)"></body></html>');

    expect($text)->toBe("Hello desk,\n\nVPN is down.\nSince 9:00.\n\n- Laptop\n- Phone")
        ->not->toContain('alert')
        ->not->toContain('<');
});

it('keeps only the new part of a reply', function (string $reply): void {
    expect(EmailBody::newPart($reply))->toBe("Still broken.\nPlease call me.");
})->with([
    'our marker' => ["Still broken.\nPlease call me.\n\n".EmailBody::MARKER."\nTicket INC-000001 was resolved."],
    'Desktop client header' => ["Still broken.\nPlease call me.\n\nFrom: IT Support <support@example.com>\nSent: Monday, 2 November 2026 10:00\nSubject: [INC-000001]"],
    'Desktop client separator' => ["Still broken.\nPlease call me.\n________________________________\nFrom: IT Support"],
    'Web mail style' => ["Still broken.\nPlease call me.\n\nOn Mon, 2 Nov 2026 at 10:00, IT Support <support@example.com> wrote:\n> Resolved."],
    'original message' => ["Still broken.\nPlease call me.\r\n-----Original Message-----\r\nFrom: x"],
]);

it('does not cut a line that merely starts with From:', function (): void {
    expect(EmailBody::newPart("From: the third floor printer\nIt jams."))->toBe("From: the third floor printer\nIt jams.");
});

it('caps the length', function (): void {
    expect(mb_strlen(EmailBody::newPart(str_repeat('a', EmailBody::MAX_LENGTH + 50))))->toBe(EmailBody::MAX_LENGTH);
});
