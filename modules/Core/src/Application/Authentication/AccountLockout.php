<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Authentication;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Modules\Core\Application\Notifications\AccountLocked;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;

/**
 * Locks a local account after consecutive failed sign-ins - wrong passwords
 * and wrong second factors alike (ADR 0057). The rate limits of AttemptLogin
 * slow guessing down; the lock stops it, at the cost that someone who knows an
 * address can lock it: the person is told by e-mail and unlocks it themselves
 * with a password reset, or an administrator does.
 *
 * The counter is written with plain queries: it is not a change people need in
 * the record's history, the lock and the unlock have their own audit events.
 */
final readonly class AccountLockout
{
    public function __construct(
        private ConnectionInterface $db,
        private Config $config,
        private AuditTrail $trail,
    ) {}

    public function threshold(): int
    {
        $value = $this->config->get('core.authentication.lockout_after', 10);

        return is_int($value) ? max(0, $value) : 10;
    }

    public function isLocked(User $user): bool
    {
        return $user->locked_at !== null;
    }

    /**
     * Counts a failure; locks the account when it reaches the threshold.
     */
    public function recordFailure(User $user): void
    {
        $threshold = $this->threshold();

        if ($threshold === 0 || $user->isDirectoryAccount() || $this->isLocked($user)) {
            return;
        }

        if ($this->db->table('users')->where('id', $user->id)->whereNull('locked_at')->incrementEach(['failed_logins' => 1]) === 0) {
            return;
        }

        $count = $this->db->table('users')->where('id', $user->id)->value('failed_logins');
        $failures = is_numeric($count) ? (int) $count : 0;

        if ($failures < $threshold) {
            return;
        }

        // Only the request that sets the lock records it and tells the person.
        $locked = $this->db->table('users')->where('id', $user->id)->whereNull('locked_at')->update(['locked_at' => now()]) > 0;

        if ($locked) {
            $user->refresh();
            $this->trail->record(AuditEvent::AccountLocked, $user, null, ['failed_logins' => $failures], $user->id);
            $user->notify(new AccountLocked);
        }
    }

    public function recordSuccess(User $user): void
    {
        if ($user->failed_logins > 0) {
            $this->db->table('users')->where('id', $user->id)->update(['failed_logins' => 0]);
        }
    }

    /**
     * @param  ?User  $actor  The administrator; null when the person reset their password, or for an operator.
     * @param  ?string  $by  Who lifted it when there is no administrator; a password reset by default.
     */
    public function unlock(User $user, ?User $actor, ?string $by = null): void
    {
        $wasLocked = $this->isLocked($user);
        $this->db->table('users')->where('id', $user->id)->update(['failed_logins' => 0, 'locked_at' => null]);
        $user->refresh();

        if ($wasLocked) {
            $this->trail->record(AuditEvent::AccountUnlocked, $user, null, ['by' => $actor !== null ? 'administrator' : ($by ?? 'password_reset')], $actor->id ?? ($by === null ? $user->id : null));
        }
    }
}
