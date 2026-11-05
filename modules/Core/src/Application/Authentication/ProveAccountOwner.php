<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Authentication;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Session\Session;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;
use Modules\Core\Http\Middleware\SetCurrentOrganization;
use SensitiveParameter;

/**
 * Proof that the person at the keyboard owns the account, before a first second
 * factor is set up: otherwise a stolen session could add its own authenticator
 * app. A local account gives its password. An account signed in through single
 * sign-on or the directory has none here, so it must have signed in just now.
 */
final readonly class ProveAccountOwner
{
    public const int ATTEMPTS_PER_MINUTE = 5;

    public const int RECENT_SIGN_IN_MINUTES = 15;

    public function __construct(
        private Hasher $hasher,
        private RateLimiter $limiter,
        private AuditTrail $trail,
        private AccountLockout $lockout,
        private Session $session,
    ) {}

    public function needsPassword(User $user): bool
    {
        return ! $user->isDirectoryAccount() && $user->password !== null;
    }

    /**
     * @throws ValidationException keyed to $field.
     */
    public function confirm(User $user, #[SensitiveParameter] string $password, string $field): void
    {
        if (! $this->needsPassword($user)) {
            $this->assertSignedInRecently($field);

            return;
        }

        $throttle = 'prove-owner:'.$user->id;

        if ($this->limiter->tooManyAttempts($throttle, self::ATTEMPTS_PER_MINUTE)) {
            throw ValidationException::withMessages([$field => __('auth.throttle', ['seconds' => $this->limiter->availableIn($throttle)])]);
        }

        if ($user->password === null || ! $this->hasher->check($password, $user->password)) {
            $this->limiter->hit($throttle, 60);
            $this->trail->record(AuditEvent::LoginFailed, $user, null, ['reason' => 'set_up_second_factor'], $user->id);
            $this->lockout->recordFailure($user);

            throw ValidationException::withMessages([$field => __('core::security.password.current_wrong')]);
        }

        $this->limiter->clear($throttle);
    }

    /**
     * @throws ValidationException keyed to $field.
     */
    private function assertSignedInRecently(string $field): void
    {
        $signedInAt = $this->session->get(SetCurrentOrganization::SIGNED_IN_AT);

        if (! is_int($signedInAt) || $signedInAt < now()->subMinutes(self::RECENT_SIGN_IN_MINUTES)->getTimestamp()) {
            throw ValidationException::withMessages([$field => __('core::security.mfa.sign_in_again', ['minutes' => self::RECENT_SIGN_IN_MINUTES])]);
        }
    }
}
