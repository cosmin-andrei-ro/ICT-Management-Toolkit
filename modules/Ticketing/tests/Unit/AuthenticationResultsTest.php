<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Ticketing\Application\Email\AuthenticationResults;

it('decides whether a header confirms the From domain', function (string $header, bool $confirmed): void {
    expect(AuthenticationResults::parse($header)->confirms('corp.example'))->toBe($confirmed);
})->with([
    'DMARC pass' => ['mx.corp.example; dmarc=pass header.from=corp.example', true],
    'DMARC pass, case and spacing' => ['mx.corp.example 1; DMARC = Pass header.from=CORP.example', true],
    'DMARC pass without a domain' => ['mx.corp.example; dmarc=pass', true],
    'DMARC pass for another domain' => ['mx.corp.example; dmarc=pass header.from=evil.example', false],
    'SPF pass for the same domain' => ['mx.corp.example; spf=pass smtp.mailfrom=agent@corp.example', true],
    'SPF pass, domain only' => ['mx.corp.example; spf=pass smtp.mailfrom=corp.example', true],
    'SPF pass for the attacker\'s own domain' => ['mx.corp.example; spf=pass smtp.mailfrom=bounce@evil.example', false],
    'SPF pass for a subdomain' => ['mx.corp.example; spf=pass smtp.mailfrom=a@mail.corp.example', false],
    'SPF pass naming no domain' => ['mx.corp.example; spf=pass', false],
    'softfail' => ['mx.corp.example; spf=softfail smtp.mailfrom=corp.example', false],
    'none' => ['mx.corp.example; dmarc=none header.from=corp.example', false],
    'only DKIM' => ['mx.corp.example; dkim=pass header.d=corp.example', false],
    'no result' => ['mx.corp.example; none', false],
    'a pass inside a comment' => ['mx.corp.example; spf=none (dmarc=pass) smtp.mailfrom=evil.example', false],
    'a method hidden in a comment' => ['mx.corp.example; spf=none (x; dmarc=pass) smtp.mailfrom=evil.example', false],
    'a method hidden in a quoted sender' => ['mx.corp.example; spf=pass smtp.mailfrom="x; dmarc=pass "@evil.example', false],
    'the domain in a quoted sender' => ['mx.corp.example; spf=pass smtp.mailfrom="a@corp.example"@evil.example', false],
    'the domain in a comment' => ['mx.corp.example; spf=pass (smtp.mailfrom=corp.example) smtp.mailfrom=evil.example', false],
    'a comment before the pass' => ['mx.corp.example; spf=pass (domain of a@corp.example designates 192.0.2.1) smtp.mailfrom=a@corp.example', true],
]);

it('sees failures, not text about failures', function (): void {
    expect(AuthenticationResults::parse('mx.corp.example; dmarc=fail (p=none)')->failed())->toBeTrue()
        ->and(AuthenticationResults::parse('mx.corp.example; spf=pass smtp.mailfrom=a.example; dmarc=fail')->failed())->toBeTrue()
        ->and(AuthenticationResults::parse('mx.corp.example; spf=softfail')->failed())->toBeFalse()
        ->and(AuthenticationResults::parse('mx.corp.example; spf=pass (not dmarc=fail) smtp.mailfrom=a.example')->failed())->toBeFalse();
});

it('reads the name of the server that wrote the header', function (): void {
    expect(AuthenticationResults::parse('MX.corp.example 1; spf=pass')->authservId)->toBe('mx.corp.example')
        ->and(AuthenticationResults::parse('  mx.corp.example  ')->authservId)->toBe('mx.corp.example');
});
