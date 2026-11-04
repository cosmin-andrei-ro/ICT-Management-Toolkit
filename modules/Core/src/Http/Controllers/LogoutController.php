<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Modules\Core\Application\Sso\Saml;
use Modules\Core\Domain\Sso\SsoProvider;
use Modules\Core\Domain\Tenancy\OrganizationScope;
use Symfony\Component\HttpFoundation\Response;

final readonly class LogoutController
{
    /** What is needed to sign out at the SAML provider too (ADR 0071). */
    public const string SAML_SESSION = 'login.saml_session';

    public function __construct(
        private AuthFactory $auth,
        private Saml $saml,
    ) {}

    public function __invoke(Request $request): Response
    {
        $samlSession = $request->session()->get(self::SAML_SESSION);
        $guard = $this->auth->guard('web');

        if ($guard instanceof StatefulGuard) {
            $guard->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $providerUrl = is_array($samlSession) ? $this->providerLogoutUrl($samlSession) : null;

        if ($providerUrl === null) {
            return redirect()->route('login');
        }

        // Not a redirect: the Content Security Policy's form-action would also apply to
        // a redirect after this form, and refuse the provider's address.
        return response()->view('core::auth.signing-out', ['url' => $providerUrl])->header('Refresh', '0; url='.$providerUrl);
    }

    /**
     * @param  array<mixed>  $session
     */
    private function providerLogoutUrl(array $session): ?string
    {
        $provider = SsoProvider::query()->withoutGlobalScope(OrganizationScope::class)
            ->whereKey($session['provider'] ?? null)->where('is_enabled', true)->first();
        $nameId = $session['name_id'] ?? null;
        $sessionIndex = $session['session_index'] ?? null;

        if ($provider === null || ! is_string($nameId)) {
            return null;
        }

        return $this->saml->logoutUrl($provider, $nameId, is_string($sessionIndex) ? $sessionIndex : null);
    }
}
