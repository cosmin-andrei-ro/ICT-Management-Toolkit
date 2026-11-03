<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Livewire\Auth;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Application\Authentication\AttemptLogin;
use Modules\Core\Application\Authentication\CompleteLogin;
use Modules\Core\Application\Authentication\SecurityKeys;
use Modules\Core\Domain\Directory\Directory;
use Modules\Core\Domain\Sso\SsoProtocol;
use Modules\Core\Domain\Sso\SsoProvider;
use Modules\Core\Domain\Tenancy\OrganizationScope;

#[Layout('core::components.layouts.guest')]
final class Login extends Component
{
    private const int SSO_LOOKUPS_PER_MINUTE = 10;

    /** Email, or AD username / DOMAIN\username / UPN. */
    public string $login = '';

    public string $password = '';

    public bool $remember = false;

    /** On an instance with several organizations: the address that finds the person's provider. */
    public string $ssoEmail = '';

    /** @var list<array{id: string, name: string, route: string}> */
    #[Locked]
    public array $ssoMatches = [];

    /**
     * Not named login(): the CSP build of Livewire would resolve wire:submit="login"
     * to the $login property and never call the action.
     */
    public function submit(AttemptLogin $attemptLogin, Request $request): void
    {
        $this->login = trim($this->login);
        $this->validate();

        try {
            $needsCode = $attemptLogin->handle($request, $this->login, $this->password, $this->remember);
        } catch (ValidationException $exception) {
            // Never send the typed password back in the component snapshot.
            $this->reset('password');

            throw $exception;
        }

        $this->reset('password');

        if ($needsCode) {
            $this->redirectRoute('login.challenge');

            return;
        }

        $this->redirectIntended(route('home'));
    }

    /**
     * Options to sign in with any passkey of this site, without a password.
     *
     * @return array<string, mixed>
     */
    public function passkeyOptions(SecurityKeys $keys): array
    {
        return $keys->requestOptions(null);
    }

    public function signInWithPasskey(string $credential, CompleteLogin $completeLogin, Request $request): void
    {
        $completeLogin->withPasskey($credential, (string) $request->ip());
        $this->redirectIntended(route('home'));
    }

    /**
     * Finds the provider by the domain of the address. A domain claimed by several
     * organizations finds none: an organization must not catch another's people by
     * naming their domain.
     */
    public function findSsoProvider(RateLimiter $limiter, Request $request): void
    {
        $this->ssoMatches = [];
        $this->validate(['ssoEmail' => ['required', 'email', 'max:255']]);
        $throttle = 'sso-find:'.sha1((string) $request->ip());

        if ($limiter->tooManyAttempts($throttle, self::SSO_LOOKUPS_PER_MINUTE)) {
            throw ValidationException::withMessages(['ssoEmail' => __('auth.throttle', ['seconds' => $limiter->availableIn($throttle)])]);
        }

        $limiter->hit($throttle, 60);
        $matches = $this->enabledProviders()->filter(fn (SsoProvider $provider): bool => $provider->allowsEmail($this->ssoEmail));

        if ($matches->isEmpty() || $matches->pluck('organization_id')->unique()->count() > 1) {
            $this->addError('ssoEmail', __('core::sso.no_provider'));

            return;
        }

        if ($matches->count() === 1) {
            $this->redirect(self::ssoRoute($matches->firstOrFail()));

            return;
        }

        $this->ssoMatches = array_values($matches->map(fn (SsoProvider $provider): array => ['id' => $provider->id, 'name' => $provider->name, 'route' => self::ssoRoute($provider)])->all());
    }

    public function render(): View
    {
        $providers = $this->enabledProviders();
        // One organization: its providers are no secret. Several: their names would say who uses this instance.
        $listed = $providers->pluck('organization_id')->unique()->count() <= 1;

        return view('core::livewire.auth.login', [
            'directoryEnabled' => app(Directory::class)->isEnabled(),
            'ssoProviders' => $listed ? $providers->map(fn (SsoProvider $provider): array => ['id' => $provider->id, 'name' => $provider->name, 'route' => self::ssoRoute($provider)])->values()->all() : $this->ssoMatches,
            'askSsoEmail' => ! $listed,
        ])->title(__('core::auth.login.title'));
    }

    /**
     * Before sign-in there is no organization: providers are looked up across all of them.
     *
     * @return Collection<int, SsoProvider>
     */
    private function enabledProviders(): Collection
    {
        return SsoProvider::query()->withoutGlobalScope(OrganizationScope::class)->where('is_enabled', true)->orderBy('name')->get();
    }

    private static function ssoRoute(SsoProvider $provider): string
    {
        return route($provider->protocol === SsoProtocol::Saml ? 'login.saml' : 'login.sso', $provider->id);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
            'remember' => ['boolean'],
        ];
    }
}
