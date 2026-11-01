<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For routes whose frontend needs eval (Filament renders inline Alpine expressions):
 * serves Livewire's standard Alpine build and relaxes the CSP for this response only.
 * Requires Livewire's published assets, so the build is chosen per request.
 */
final readonly class AllowUnsafeEval
{
    public const string ATTRIBUTE = 'core.csp.allow_unsafe_eval';

    private const string PREVIOUS_SETTING = 'core.csp.previous_livewire_csp_safe';

    public function __construct(private Config $config) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::ATTRIBUTE, true);
        $request->attributes->set(self::PREVIOUS_SETTING, $this->config->get('livewire.csp_safe'));

        $this->config->set('livewire.csp_safe', false);

        return $next($request);
    }

    /**
     * Restored only here: Livewire injects its script tag on RequestHandled, after the
     * middleware pipeline has returned. Long-lived workers (Octane) must not keep the
     * relaxed setting for the next request.
     */
    public function terminate(Request $request, Response $response): void
    {
        if ($request->attributes->has(self::PREVIOUS_SETTING)) {
            $this->config->set('livewire.csp_safe', $request->attributes->get(self::PREVIOUS_SETTING));
        }
    }
}
