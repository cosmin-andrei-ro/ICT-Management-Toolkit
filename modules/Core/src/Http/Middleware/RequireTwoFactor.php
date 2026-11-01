<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Application\Authentication\TwoFactorAuthentication;
use Modules\Core\Domain\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * People who must use two-factor authentication and have not set it up yet can
 * only reach the page where they set it up (and sign out).
 */
final readonly class RequireTwoFactor
{
    public function __construct(private TwoFactorAuthentication $twoFactor) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $this->twoFactor->isEnabled($user) && $this->twoFactor->isRequiredFor($user)) {
            // Livewire updates and JSON calls cannot follow a redirect to another page.
            abort_if($request->hasHeader('X-Livewire') || $request->expectsJson(), 403, __('core::security.required'));

            return redirect()->route('account.security');
        }

        return $next($request);
    }
}
