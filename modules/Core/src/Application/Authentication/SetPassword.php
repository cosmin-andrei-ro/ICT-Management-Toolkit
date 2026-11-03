<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Authentication;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;
use SensitiveParameter;

/**
 * Sets a local account's password. Every other session and "keep me signed in"
 * cookie stops working (the auth.session middleware compares password hashes).
 */
final readonly class SetPassword
{
    /** Wrong current passwords allowed per minute, as at sign-in. */
    public const int ATTEMPTS_PER_MINUTE = 5;

    public function __construct(
        private Hasher $hasher,
        private AuditTrail $trail,
        private AccountLockout $lockout,
        private RateLimiter $limiter,
        private PasswordPolicy $policy,
    ) {}

    /**
     * @throws ValidationException keyed to "password" or "current_password".
     */
    public function change(User $user, #[SensitiveParameter] string $current, #[SensitiveParameter] string $new, #[SensitiveParameter] string $confirmation): void
    {
        $throttle = 'password-change:'.$user->id;

        // A stolen session must not become a way to guess the password without limit.
        if ($this->limiter->tooManyAttempts($throttle, self::ATTEMPTS_PER_MINUTE)) {
            throw ValidationException::withMessages(['current_password' => __('auth.throttle', ['seconds' => $this->limiter->availableIn($throttle)])]);
        }

        if ($user->isDirectoryAccount() || $user->password === null || ! $this->hasher->check($current, $user->password)) {
            $this->limiter->hit($throttle, 60);
            $this->trail->record(AuditEvent::LoginFailed, $user, null, ['reason' => 'current_password'], $user->id);
            $this->lockout->recordFailure($user);

            throw ValidationException::withMessages(['current_password' => __('core::security.password.current_wrong')]);
        }

        $this->limiter->clear($throttle);

        $this->set($user, $new, $confirmation, AuditEvent::PasswordChanged);
    }

    /**
     * After a reset or an invitation link: the link proved control of the mailbox.
     * A reset is how someone takes their account back, so API tokens made by
     * whoever had it stop working too.
     *
     * @throws ValidationException keyed to "password".
     */
    public function reset(User $user, #[SensitiveParameter] string $new, #[SensitiveParameter] string $confirmation): void
    {
        $this->set($user, $new, $confirmation, AuditEvent::PasswordReset);
        $user->tokens()->delete();
        // The link proved control of the mailbox: that also unlocks a locked account.
        $this->lockout->unlock($user, null);
    }

    private function set(User $user, #[SensitiveParameter] string $new, #[SensitiveParameter] string $confirmation, AuditEvent $event): void
    {
        if ($user->isDirectoryAccount()) {
            throw new InvalidArgumentException('Active Directory accounts have no password here.');
        }

        Validator::make(
            ['password' => $new, 'password_confirmation' => $confirmation],
            ['password' => ['required', 'string', 'confirmed', $this->policy->ruleFor($user)]],
        )->validate();

        $user->forceFill([
            'password' => $new,
            'remember_token' => Str::random(60),
        ])->save();

        $this->trail->record($event, $user, null, null, $user->id);
    }
}
