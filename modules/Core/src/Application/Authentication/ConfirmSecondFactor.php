<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Authentication;

use Illuminate\Cache\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;
use SensitiveParameter;

/**
 * A signed-in person proving their second factor again before a sensitive
 * change to their own account. Throttled, audited and counted towards the
 * lockout like the sign-in challenge: otherwise a stolen session could guess
 * codes here without limit and then mint new recovery codes.
 */
final readonly class ConfirmSecondFactor
{
    public const int ATTEMPTS_PER_MINUTE = 5;

    public function __construct(
        private TwoFactorAuthentication $twoFactor,
        private RateLimiter $limiter,
        private AuditTrail $trail,
        private AccountLockout $lockout,
    ) {}

    /**
     * @throws ValidationException keyed to $field.
     */
    public function confirm(User $user, #[SensitiveParameter] string $code, string $purpose, string $field): void
    {
        $throttle = 'reauthenticate:'.$user->id;

        if ($this->limiter->tooManyAttempts($throttle, self::ATTEMPTS_PER_MINUTE)) {
            throw ValidationException::withMessages([$field => __('auth.throttle', ['seconds' => $this->limiter->availableIn($throttle)])]);
        }

        if (! $this->twoFactor->verify($user, $code)) {
            $this->limiter->hit($throttle, 60);
            $this->trail->record(AuditEvent::MfaFailed, $user, null, ['purpose' => $purpose], $user->id);
            $this->lockout->recordFailure($user);

            throw ValidationException::withMessages([$field => __('core::security.challenge.invalid')]);
        }

        $this->limiter->clear($throttle);
    }
}
