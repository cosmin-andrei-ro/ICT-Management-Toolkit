<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Authentication;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
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
use SensitiveParameter;

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

    /** Failed sign-ins from one address per minute, whatever the accounts: stops spraying many accounts at once. */
    private const int MAX_ATTEMPTS_PER_ADDRESS = 30;

    public function __construct(
        private AuthFactory $auth,
        private RateLimiter $limiter,
        private Dispatcher $events,
        private Directory $directory,
        private SyncDirectoryUser $syncDirectoryUser,
        private AuditTrail $trail,
        private ExceptionHandler $exceptions,
        private CompleteLogin $completeLogin,
        private AccountLockout $lockout,
    ) {}

    /**
     * @param  string  $identifier  Email for local accounts; username, DOMAIN\username, UPN or email for AD.
     * @return bool Whether the user must now give a second factor (CompleteLogin::withCode).
     *
     * @throws ValidationException keyed to "login".
     */
    public function handle(Request $request, string $identifier, #[SensitiveParameter] string $password, bool $remember): bool
    {
        $identifier = Str::lower(trim($identifier));
        $throttleKeys = [
            'login:client:'.sha1($identifier.'|'.$request->ip()) => [self::MAX_ATTEMPTS_PER_CLIENT, 60],
            'login:account:'.sha1($identifier) => [self::MAX_ATTEMPTS_PER_ACCOUNT, 15 * 60],
            'login:address:'.sha1((string) $request->ip()) => [self::MAX_ATTEMPTS_PER_ADDRESS, 60],
        ];

        $this->ensureNotThrottled($request, $throttleKeys);

        $local = $this->usesLocalAccount($identifier);
        $user = $local
            ? $this->attemptLocal($identifier, $password)
            : $this->attemptDirectory($identifier, $password);

        if ($user === null) {
            foreach ($throttleKeys as $key => [, $decaySeconds]) {
                $this->limiter->hit($key, $decaySeconds);
            }

            throw ValidationException::withMessages(['login' => __('auth.failed')]);
        }

        $this->limiter->clear((string) array_key_first($throttleKeys));

        // Directory accounts never get a "remember me" cookie: a long-lived cookie
        // would outlive a deactivation in AD.
        return $this->completeLogin->afterPassword($user, $remember && $local);
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

    /**
     * Checks the password without signing in: the second factor may still be needed.
     */
    private function attemptLocal(string $email, #[SensitiveParameter] string $password): ?User
    {
        // Inactive accounts fail exactly like a wrong password, so the response does
        // not reveal which accounts exist or are disabled.
        $credentials = ['email' => $email, 'password' => $password, 'is_active' => true, 'auth_source' => AuthSource::Local->value];
        $guard = $this->guard();
        $account = User::query()->where('email', $email)->where('auth_source', AuthSource::Local)->first();

        if (! $guard instanceof SessionGuard) {
            throw new LogicException('The web guard must be session based.');
        }

        // A locked account fails like a wrong password, whatever the password: the e-mail sent at locking says why.
        // The password is still checked, and ignored, so a locked account answers as slowly as any other.
        if ($account !== null && $this->lockout->isLocked($account)) {
            $guard->validate($credentials);
            $this->trail->record(AuditEvent::LoginFailed, $account, null, ['email' => $email, 'reason' => 'locked'], $account->id);

            return null;
        }

        if (! $guard->validate($credentials)) {
            $attempted = $guard->getLastAttempted();
            $this->events->dispatch(new Failed('web', $attempted instanceof User ? $attempted : null, ['email' => $email]));

            if ($account !== null) {
                $this->lockout->recordFailure($account);
            }

            return null;
        }

        $user = $guard->getLastAttempted();

        return $user instanceof User ? $user : null;
    }

    private function attemptDirectory(string $identifier, #[SensitiveParameter] string $password): ?User
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
