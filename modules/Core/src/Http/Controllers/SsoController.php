<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Core\Application\Authentication\CompleteLogin;
use Modules\Core\Application\Sso\OpenIdConnect;
use Modules\Core\Application\Sso\SignInWithSso;
use Modules\Core\Application\Sso\SsoFailed;
use Modules\Core\Domain\Sso\SsoProtocol;
use Modules\Core\Domain\Sso\SsoProvider;
use Modules\Core\Domain\Tenancy\OrganizationScope;

/**
 * Single sign-on through an organization's identity provider (OpenID Connect,
 * ADR 0038): off to the provider, and back with the person signed in there.
 */
final readonly class SsoController
{
    public function __construct(
        private OpenIdConnect $oidc,
        private ExceptionHandler $exceptions,
    ) {}

    public function redirect(string $provider): RedirectResponse
    {
        $ssoProvider = $this->provider($provider);

        try {
            return redirect()->away($this->oidc->authorizationUrl($ssoProvider, route('login.sso.callback', $ssoProvider->id)));
        } catch (SsoFailed $failure) {
            return $this->failed($failure);
        }
    }

    public function callback(string $provider, Request $request, SignInWithSso $signIn, CompleteLogin $completeLogin): RedirectResponse
    {
        $ssoProvider = $this->provider($provider);
        $state = $request->query('state');
        $code = $request->query('code');

        if (! is_string($state) || ! is_string($code) || $state === '' || $code === '') {
            // The provider sends "error" when the person cancelled or was refused there.
            return $this->failed(SsoFailed::because(is_string($request->query('error')) ? 'provider_error' : 'state'));
        }

        try {
            $claims = $this->oidc->claims($ssoProvider, $state, $code);
            $user = $signIn->handle($ssoProvider, $claims);
        } catch (SsoFailed $failure) {
            return $this->failed($failure);
        }

        $needsCode = $completeLogin->afterSso($user, $ssoProvider->trust_mfa && $signIn->mfaAsserted($claims));

        return $needsCode ? redirect()->route('login.challenge') : redirect()->intended(route('home'));
    }

    private function provider(string $id): SsoProvider
    {
        // Before sign-in there is no organization context: providers are looked up across organizations.
        $provider = SsoProvider::query()->withoutGlobalScope(OrganizationScope::class)
            ->whereKey($id)->where('protocol', SsoProtocol::Oidc->value)->where('is_enabled', true)->first();
        abort_if($provider === null, 404);

        return $provider;
    }

    private function failed(SsoFailed $failure): RedirectResponse
    {
        $this->exceptions->report($failure);

        return redirect()->route('login')->with('sso_error', __('core::sso.failed'));
    }
}
