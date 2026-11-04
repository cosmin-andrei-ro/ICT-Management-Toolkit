<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Core\Application\Authentication\CompleteLogin;
use Modules\Core\Application\Authentication\EndAllSessions;
use Modules\Core\Application\Sso\Saml;
use Modules\Core\Application\Sso\SignInWithSso;
use Modules\Core\Application\Sso\SsoFailed;
use Modules\Core\Domain\Sso\SsoIdentity;
use Modules\Core\Domain\Sso\SsoProtocol;
use Modules\Core\Domain\Sso\SsoProvider;
use Modules\Core\Domain\Tenancy\OrganizationScope;

/**
 * Single sign-on through an organization's SAML 2.0 identity provider (ADR
 * 0039): off to the provider, back to the assertion consumer service, and the
 * metadata to register the toolkit there.
 */
final readonly class SamlController
{
    public function __construct(
        private Saml $saml,
        private ExceptionHandler $exceptions,
    ) {}

    public function redirect(string $provider, Request $request): RedirectResponse
    {
        try {
            return redirect()->away($this->saml->loginUrl($this->provider($provider), $request));
        } catch (SsoFailed $failure) {
            return $this->failed($failure);
        }
    }

    /**
     * The provider posts its answer here from its own site: no CSRF token, no
     * session cookie; the signed assertion, the single-use request ID and the
     * cookie of the browser that asked stand in for them.
     */
    public function consume(string $provider, Request $request, SignInWithSso $signIn, CompleteLogin $completeLogin): RedirectResponse
    {
        $ssoProvider = $this->provider($provider);
        $answer = $request->input('SAMLResponse');

        if (! is_string($answer) || $answer === '') {
            return $this->failed(SsoFailed::because('saml_response'));
        }

        try {
            $claims = $this->saml->claims($ssoProvider, $answer, $request);
            $user = $signIn->handle($ssoProvider, $claims);
        } catch (SsoFailed $failure) {
            return $this->failed($failure);
        }

        $needsCode = $completeLogin->afterSso($user, $ssoProvider->trust_mfa && $signIn->mfaAsserted($claims));
        $request->session()->put(LogoutController::SAML_SESSION, [
            'provider' => $ssoProvider->id,
            'name_id' => $claims['sub'] ?? null,
            'session_index' => $claims['session_index'] ?? null,
        ]);

        return $needsCode ? redirect()->route('login.challenge') : redirect()->intended(route('home'));
    }

    /**
     * Single logout, through the browser. The provider's answer to our own request
     * needs nothing: the session here ended before the browser left. Its request is
     * answered the same whether or not it names an account here.
     */
    public function singleLogout(string $provider, Request $request, EndAllSessions $endSessions): RedirectResponse
    {
        $ssoProvider = $this->provider($provider);
        abort_if($ssoProvider->slo_url === null || $ssoProvider->slo_url === '', 404);

        if ($request->query('SAMLRequest') === null) {
            return redirect()->route('login');
        }

        try {
            $logout = $this->saml->logoutRequest($ssoProvider);
        } catch (SsoFailed $failure) {
            $this->exceptions->report($failure);
            abort(400);
        }

        $identity = SsoIdentity::query()->with('user')->where('provider_id', $ssoProvider->id)->where('subject', $logout['nameId'])->first();

        if ($identity !== null) {
            $endSessions->handle($identity->user, 'saml_single_logout');
        }

        return redirect()->away($logout['responseUrl']);
    }

    public function metadata(string $provider): Response
    {
        try {
            $xml = $this->saml->metadata($this->provider($provider));
        } catch (SsoFailed) {
            abort(404);
        }

        return response($xml, 200, ['Content-Type' => 'application/samlmetadata+xml']);
    }

    private function provider(string $id): SsoProvider
    {
        // Before sign-in there is no organization context: providers are looked up across organizations.
        $provider = SsoProvider::query()->withoutGlobalScope(OrganizationScope::class)
            ->whereKey($id)->where('protocol', SsoProtocol::Saml->value)->where('is_enabled', true)->first();
        abort_if($provider === null, 404);

        return $provider;
    }

    private function failed(SsoFailed $failure): RedirectResponse
    {
        $this->exceptions->report($failure);

        return redirect()->route('login')->with('sso_error', __('core::sso.failed'));
    }
}
