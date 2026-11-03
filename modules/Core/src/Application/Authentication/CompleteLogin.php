<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Authentication;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Directory\AuthSource;
use Modules\Core\Domain\Models\User;
use SensitiveParameter;

/**
 * Signs a user in, or, when they use two-factor authentication, holds the
 * sign-in until they give a code or use a security key. The pending sign-in
 * lives in the session for five minutes and is tied to it: nothing is sent to
 * the browser. A passkey signs in on its own: it is something the person
 * holds, unlocked by their PIN or fingerprint.
 */
final readonly class CompleteLogin
{
    private const string PENDING = 'login.pending';

    /** Set for a session whose second factor was asked by a trusted identity provider. */
    public const string MFA_BY_PROVIDER = 'login.mfa_by_provider';

    private const int PENDING_SECONDS = 300;

    /** Codes per account per minute: a 6-digit code must not be guessable. */
    private const int MAX_CODES_PER_MINUTE = 5;

    /** Passkey attempts per address per minute. */
    private const int MAX_PASSKEYS_PER_MINUTE = 10;

    public function __construct(
        private AuthFactory $auth,
        private Session $session,
        private TwoFactorAuthentication $twoFactor,
        private SecurityKeys $keys,
        private RateLimiter $limiter,
        private AuditTrail $trail,
        private AccountLockout $lockout,
    ) {}

    /**
     * @return bool Whether a second factor is still needed.
     */
    public function afterPassword(User $user, bool $remember): bool
    {
        // Prevents session fixation.
        $this->session->regenerate();

        if (! $this->twoFactor->isEnabled($user)) {
            $this->signIn($user, $remember);

            return false;
        }

        $this->session->put(self::PENDING, ['user_id' => $user->id, 'remember' => $remember, 'expires_at' => now()->getTimestamp() + self::PENDING_SECONDS]);

        return true;
    }

    /**
     * After single sign-on: signed in at once when the identity provider is
     * trusted to have asked for a second factor and says it did; otherwise as
     * after a password. Only for accounts the provider created: a local or AD
     * account linked by e-mail keeps its own second factor, so whoever sets up
     * a provider cannot sign in as an existing administrator without it.
     *
     * @return bool Whether a second factor is still needed.
     */
    public function afterSso(User $user, bool $mfaByProvider): bool
    {
        if (! $mfaByProvider || $user->auth_source !== AuthSource::Sso) {
            return $this->afterPassword($user, false);
        }

        $this->session->regenerate();
        $this->signIn($user, false);
        $this->session->put(self::MFA_BY_PROVIDER, true);

        return false;
    }

    public function pendingUser(): ?User
    {
        $pending = $this->session->get(self::PENDING);

        if (! is_array($pending) || ! is_string($pending['user_id'] ?? null) || ! is_int($pending['expires_at'] ?? null) || $pending['expires_at'] < now()->getTimestamp()) {
            return null;
        }

        $user = User::query()->find($pending['user_id']);

        // Locked by wrong codes while the sign-in was pending: it has to start again, and fails.
        return $user !== null && $user->is_active && $user->canSignIn() && ! $this->lockout->isLocked($user) ? $user : null;
    }

    /**
     * @throws ValidationException keyed to "code".
     */
    public function withCode(#[SensitiveParameter] string $code): User
    {
        $user = $this->pendingUser() ?? throw ValidationException::withMessages(['code' => __('core::security.challenge.expired')]);
        $key = 'login:mfa:'.$user->id;

        if ($this->limiter->tooManyAttempts($key, self::MAX_CODES_PER_MINUTE)) {
            throw ValidationException::withMessages(['code' => __('auth.throttle', ['seconds' => $this->limiter->availableIn($key)])]);
        }

        if (! $this->twoFactor->verify($user, $code)) {
            $this->limiter->hit($key, 60);
            $this->trail->record(AuditEvent::MfaFailed, $user, null, null, $user->id);
            $this->lockout->recordFailure($user);

            throw ValidationException::withMessages(['code' => __('core::security.challenge.invalid')]);
        }

        $this->limiter->clear($key);
        $pending = $this->session->pull(self::PENDING);
        $this->session->regenerate();
        $this->signIn($user, is_array($pending) && ($pending['remember'] ?? false) === true);

        return $user;
    }

    /**
     * The second factor given with one of the person's security keys.
     *
     * @throws ValidationException keyed to "code".
     */
    public function withSecurityKey(string $credentialJson): User
    {
        $user = $this->pendingUser() ?? throw ValidationException::withMessages(['code' => __('core::security.challenge.expired')]);
        $key = 'login:mfa:'.$user->id;

        if ($this->limiter->tooManyAttempts($key, self::MAX_CODES_PER_MINUTE)) {
            throw ValidationException::withMessages(['code' => __('auth.throttle', ['seconds' => $this->limiter->availableIn($key)])]);
        }

        try {
            $this->keys->authenticate($user, $credentialJson);
        } catch (InvalidArgumentException) {
            $this->limiter->hit($key, 60);
            $this->trail->record(AuditEvent::MfaFailed, $user, null, ['method' => 'security_key'], $user->id);
            $this->lockout->recordFailure($user);

            throw ValidationException::withMessages(['code' => __('core::security.keys.failed')]);
        }

        $this->limiter->clear($key);
        $pending = $this->session->pull(self::PENDING);
        $this->session->regenerate();
        $this->signIn($user, is_array($pending) && ($pending['remember'] ?? false) === true);

        return $user;
    }

    /**
     * Signs in with a passkey alone, without a password.
     *
     * @throws ValidationException keyed to "login".
     */
    public function withPasskey(string $credentialJson, string $ip): User
    {
        $key = 'login:passkey:'.sha1($ip);

        if ($this->limiter->tooManyAttempts($key, self::MAX_PASSKEYS_PER_MINUTE)) {
            throw ValidationException::withMessages(['login' => __('auth.throttle', ['seconds' => $this->limiter->availableIn($key)])]);
        }

        try {
            $user = $this->keys->authenticate(null, $credentialJson);
        } catch (InvalidArgumentException) {
            $this->limiter->hit($key, 60);
            $this->trail->record(AuditEvent::LoginFailed, null, null, ['method' => 'passkey']);

            throw ValidationException::withMessages(['login' => __('core::security.keys.passkey_failed')]);
        }

        $this->session->regenerate();
        $this->signIn($user, false);

        return $user;
    }

    /**
     * @throws ValidationException keyed to "login" for a locked account, whatever the method.
     */
    private function signIn(User $user, bool $remember): void
    {
        if ($this->lockout->isLocked($user)) {
            $this->trail->record(AuditEvent::LoginFailed, $user, null, ['reason' => 'locked'], $user->id);

            throw ValidationException::withMessages(['login' => __('auth.failed')]);
        }

        $this->lockout->recordSuccess($user);
        $this->guard()->login($user, $remember);
        $user->forceFill(['last_login_at' => now()])->save();
    }

    private function guard(): StatefulGuard
    {
        $guard = $this->auth->guard('web');

        if (! $guard instanceof StatefulGuard) {
            throw new LogicException('The web guard must be session based.');
        }

        return $guard;
    }
}
