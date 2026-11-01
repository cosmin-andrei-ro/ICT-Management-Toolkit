<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers for every response (CLAUDE.md §6).
 *
 * The CSP allows scripts only from this origin or with the per-request nonce, and
 * no eval: Livewire runs with the CSP-safe Alpine build. Routes that cannot work
 * without eval (the Filament panel) opt out through AllowUnsafeEval.
 */
final readonly class SecurityHeaders
{
    public function __construct(private Vite $vite) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = $this->vite->useCspNonce();

        $response = $next($request);
        $headers = $response->headers;

        // A response may carry a stricter policy of its own (e.g. file downloads).
        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', $this->contentSecurityPolicy(
                $nonce,
                allowEval: $request->attributes->getBoolean(AllowUnsafeEval::ATTRIBUTE),
            ));
        }

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function contentSecurityPolicy(string $nonce, bool $allowEval): string
    {
        $devServer = $this->viteDevServer();

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", ...($allowEval ? ["'unsafe-eval'"] : []), ...$devServer['http']],
            // Livewire and Alpine set inline style attributes (x-show, transitions).
            'style-src' => ["'self'", "'unsafe-inline'", ...$devServer['http']],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'font-src' => ["'self'", 'data:'],
            'connect-src' => ["'self'", ...$devServer['http'], ...$devServer['ws']],
            'object-src' => ["'none'"],
            'base-uri' => ["'none'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        return implode('; ', array_map(
            fn (string $directive, array $sources): string => $directive.' '.implode(' ', $sources),
            array_keys($directives),
            $directives,
        ));
    }

    /**
     * Origins of the Vite dev server, only while `npm run dev` is running.
     *
     * @return array{http: list<string>, ws: list<string>}
     */
    private function viteDevServer(): array
    {
        if (! $this->vite->isRunningHot()) {
            return ['http' => [], 'ws' => []];
        }

        $url = trim((string) file_get_contents($this->vite->hotFile()));
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);

        if (! is_string($scheme) || ! is_string($host)) {
            return ['http' => [], 'ws' => []];
        }

        $authority = $host.($port !== null ? ":{$port}" : '');

        return [
            'http' => ["{$scheme}://{$authority}"],
            'ws' => [($scheme === 'https' ? 'wss' : 'ws')."://{$authority}"],
        ];
    }
}
