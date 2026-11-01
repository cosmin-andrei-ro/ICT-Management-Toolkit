<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

afterEach(fn () => TrustProxies::flushState());

function forwardedRequest(object $test, string $from): array
{
    Route::get('/_proxy-probe', fn (Request $request) => ['ip' => $request->ip(), 'secure' => $request->isSecure()]);

    return $test->withServerVariables(['REMOTE_ADDR' => $from])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https'])
        ->get('/_proxy-probe')
        ->json();
}

it('ignores forwarded headers unless the proxy is trusted', function (): void {
    expect(forwardedRequest($this, '10.0.0.5'))->toBe(['ip' => '10.0.0.5', 'secure' => false]);
});

it('believes a configured proxy', function (): void {
    TrustProxies::at(['10.0.0.0/24']);

    expect(forwardedRequest($this, '10.0.0.5'))->toBe(['ip' => '203.0.113.9', 'secure' => true]);
});

it('does not believe other addresses when a proxy is configured', function (): void {
    TrustProxies::at(['10.0.0.0/24']);

    expect(forwardedRequest($this, '10.9.0.5'))->toBe(['ip' => '10.9.0.5', 'secure' => false]);
});
