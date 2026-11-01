<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Authentication;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Modules\Core\Application\Directory\SyncDirectoryUser;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Directory\AuthSource;
use Modules\Core\Domain\Directory\Directory;
use Modules\Core\Domain\Directory\DirectoryAccessDenied;
use Modules\Core\Domain\Directory\DirectoryUnavailable;
use Modules\Core\Domain\Models\User;

/**
 * Signs a user in with a local or an Active Directory account (ADR 0007).
 *
 * Each account has a fixed source: a local account is only checked against its
 * local password, a directory account only against AD. Identifiers that match no
 * local account are looked up in AD when it is enabled.
 */
final readonly class AttemptLogin
{
    /** Attempts per identifier and IP address, per minute. */
    private const int MAX_ATTEMPTS_PER_CLIENT = 5;

    /** Attempts per identifier from any address, per 15 minutes: slows distributed guessing. */
    private const int MAX_ATTEMPTS_PER_ACCOUNT = 20;

    public function __construct(
        private AuthFactory $auth,
        private RateLimiter $limiter,
        private Dispatcher $events,
        private Session $session,
        private Directory $directory,
        private SyncDirectoryUser $syncDirectoryUser,
        private AuditTrail $trail,
        private ExceptionHandler $exceptions,
    ) {}

    /**
     * @param  string  $identifier  Email for local accounts; username, DOMAIN\username, UPN or email for AD.
     *
     * @throws ValidationException keyed to "login".
     */
    public function handle(Request $request, string $identifier, string $password, bool $remember): User
    {
        $identifier = Str::lower(trim($identifier));
        $throttleKeys = [
            'login:client:'.sha1($identifier.'|'.$request->ip()) => [self::MAX_ATTEMPTS_PER_CLIENT, 60],
            'login:account:'.sha1($identifier) => [self::MAX_ATTEMPTS_PER_ACCOUNT, 15 * 60],
        ];

        $this->ensureNotThrottled($request, $throttleKeys);

        $user = $this->usesLocalAccount($identifier)
            ? $this->attemptLocal($identifier, $password, $remember)
            : $this->attemptDirectory($identifier, $password);

        if ($user === null) {
            foreach ($throttleKeys as $key => [, $decaySeconds]) {
                $this->limiter->hit($key, $decaySeconds);
            }

            throw ValidationException::withMessages(['login' => __('auth.failed')]);
        }

        $this->limiter->clear((string) array_key_first($throttleKeys));

        // Prevents session fixation.
        $this->session->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return $user;
    }

    private function usesLocalAccount(string $identifier): bool
    {
        if (! $this->directory->isEnabled()) {
            return true;
        }

        return User::query()
            ->where('auth_source', AuthSource::Local)
            ->where('email', $identifier)
            ->exists();
    }

    private function attemptLocal(string $email, string $password, bool $remember): ?User
    {
        // Inactive accounts fail exactly like a wrong password, so the response does
        // not reveal which accounts exist or are disabled.
        $credentials = ['email' => $email, 'password' => $password, 'is_active' => true, 'auth_source' => AuthSource::Local->value];

        if (! $this->guard()->attempt($credentials, $remember)) {
            return null;
        }

        $user = $this->guard()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Directory accounts never get a "remember me" cookie: a long-lived cookie would
     * outlive a deactivation in AD.
     */
    private function attemptDirectory(string $identifier, string $password): ?User
    {
        try {
            $directoryUser = $this->directory->findUser($identifier);

            if ($directoryUser === null || ! $this->directory->authenticate($directoryUser, $password)) {
                $existing = $directoryUser !== null
                    ? User::query()->where('directory_guid', $directoryUser->guid)->first()
                    : null;

                $this->events->dispatch(new Failed('web', $existing, ['email' => $identifier]));

                return null;
            }

            $groups = $this->directory->groupsOf($directoryUser);
        } catch (DirectoryUnavailable $exception) {
            $this->exceptions->report($exception);

            throw ValidationException::withMessages(['login' => __('core::auth.directory_unavailable')]);
        }

        try {
            $user = $this->syncDirectoryUser->handle($directoryUser, $groups);
        } catch (DirectoryAccessDenied $denied) {
            $subject = User::query()->withTrashed()->where('directory_guid', $directoryUser->guid)->first();

            $this->trail->record(AuditEvent::LoginDenied, $subject, null, [
                'email' => $identifier,
                'reason' => $denied->reason,
            ]);

            // The password was right, so a distinct message tells an attacker nothing new.
            throw ValidationException::withMessages(['login' => __('core::auth.access_denied')]);
        }

        $this->guard()->login($user);

        return $user;
    }

    /**
     * @param  array<string, array{int, int}>  $throttleKeys
     */
    private function ensureNotThrottled(Request $request, array $throttleKeys): void
    {
        foreach ($throttleKeys as $key => [$maxAttempts]) {
            if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
                $this->events->dispatch(new Lockout($request));

                throw ValidationException::withMessages([
                    'login' => __('auth.throttle', ['seconds' => $this->limiter->availableIn($key)]),
                ]);
            }
        }
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
