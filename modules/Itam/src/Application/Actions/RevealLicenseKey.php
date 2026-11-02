<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Actions;

use Illuminate\Cache\RateLimiter;
use Modules\Core\Application\Authentication\TwoFactorAuthentication;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;
use Modules\Itam\Domain\Models\License;
use SensitiveParameter;

/**
 * Shows a licence key to someone allowed to see it, after a fresh code from
 * their authenticator app (re-authentication, CLAUDE.md §6). Every showing and
 * every wrong code is in the audit trail; attempts are rate limited.
 */
final readonly class RevealLicenseKey
{
    private const int ATTEMPTS_PER_MINUTE = 5;

    public function __construct(
        private TwoFactorAuthentication $twoFactor,
        private AuditTrail $trail,
        private RateLimiter $limiter,
    ) {}

    /**
     * @return ?string The key; null when the code is wrong, attempts are exhausted, or there is no key.
     */
    public function handle(License $license, User $viewer, #[SensitiveParameter] string $code): ?string
    {
        $throttle = 'itam-reveal:'.$viewer->id;

        if ($this->limiter->tooManyAttempts($throttle, self::ATTEMPTS_PER_MINUTE)) {
            return null;
        }

        if (! $this->twoFactor->verify($viewer, $code)) {
            $this->limiter->hit($throttle, 60);
            $this->trail->record(AuditEvent::MfaFailed, $viewer, null, ['purpose' => 'reveal_license_key', 'license' => $license->name], $viewer->id);

            return null;
        }

        $this->limiter->clear($throttle);
        $key = $license->license_key;

        if ($key !== null) {
            $this->trail->record(AuditEvent::SecretRevealed, $license, null, ['secret' => 'license_key'], $viewer->id);
        }

        return $key;
    }

    public function isThrottled(User $viewer): bool
    {
        return $this->limiter->tooManyAttempts('itam-reveal:'.$viewer->id, self::ATTEMPTS_PER_MINUTE);
    }
}
