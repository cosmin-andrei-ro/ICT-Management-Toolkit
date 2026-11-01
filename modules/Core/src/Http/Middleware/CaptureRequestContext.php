<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Core\Domain\Audit\RequestContext;
use Symfony\Component\HttpFoundation\Response;

final readonly class CaptureRequestContext
{
    public function __construct(private RequestContext $context) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->context->ipAddress = $request->ip();
        $this->context->userAgent = mb_substr((string) $request->userAgent(), 0, 1000) ?: null;
        $this->context->requestId = $this->requestId($request);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $this->context->requestId);

        return $response;
    }

    /**
     * Keeps an id set by a reverse proxy for log correlation, but only when it looks
     * like an identifier: the value is client-controlled.
     */
    private function requestId(Request $request): string
    {
        $header = $request->headers->get('X-Request-Id');

        return $header !== null && preg_match('/\A[A-Za-z0-9._-]{1,64}\z/', $header) === 1
            ? $header
            : (string) Str::uuid7();
    }
}
