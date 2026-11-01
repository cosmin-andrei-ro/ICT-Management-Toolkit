<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Authentication;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Modules\Core\Domain\Models\User;

final readonly class AttemptLogin
{
    /** Attempts per email and IP address, per minute. */
    private const int MAX_ATTEMPTS_PER_CLIENT = 5;

    /** Attempts per email from any address, per 15 minutes: slows distributed guessing. */
    private const int MAX_ATTEMPTS_PER_ACCOUNT = 20;

    public function __construct(
        private AuthFactory $auth,
        private RateLimiter $limiter,
        private Dispatcher $events,
        private Session $session,
    ) {}

    /**
     * @throws ValidationException on throttling or invalid credentials, keyed to "email".
     */
    public function handle(Request $request, string $email, string $password, bool $remember): User
    {
        $email = Str::lower(trim($email));
        $clientKey = 'login:client:'.sha1($email.'|'.$request->ip());
        $accountKey = 'login:account:'.sha1($email);

        foreach ([$clientKey => self::MAX_ATTEMPTS_PER_CLIENT, $accountKey => self::MAX_ATTEMPTS_PER_ACCOUNT] as $key => $max) {
            if ($this->limiter->tooManyAttempts($key, $max)) {
                $this->events->dispatch(new Lockout($request));

                throw ValidationException::withMessages([
                    'email' => __('auth.throttle', ['seconds' => $this->limiter->availableIn($key)]),
                ]);
            }
        }

        // Inactive accounts fail exactly like a wrong password, so the response does
        // not reveal which accounts exist or are disabled.
        $credentials = ['email' => $email, 'password' => $password, 'is_active' => true];

        if (! $this->guard()->attempt($credentials, $remember)) {
            $this->limiter->hit($clientKey, 60);
            $this->limiter->hit($accountKey, 15 * 60);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $this->limiter->clear($clientKey);
        // Prevents session fixation.
        $this->session->regenerate();

        /** @var User $user */
        $user = $this->guard()->user();
        $user->forceFill(['last_login_at' => now()])->save();

        return $user;
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
