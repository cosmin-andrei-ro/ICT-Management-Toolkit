<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Authentication;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;

/**
 * Authentication is part of the security audit log (CLAUDE.md §6). Passwords are
 * never recorded; the attempted email is, as it is needed to investigate attacks.
 */
final readonly class RecordAuthenticationEvent
{
    public function __construct(private AuditTrail $trail) {}

    public function handle(Login|Failed|Logout|Lockout $event): void
    {
        match (true) {
            $event instanceof Login => $this->recordForUser(AuditEvent::Login, $event->user, ['remember' => $event->remember]),
            $event instanceof Logout => $this->recordForUser(AuditEvent::Logout, $event->user, null),
            $event instanceof Failed => $this->recordFailure($event),
            $event instanceof Lockout => $this->trail->record(AuditEvent::Lockout, null, null, [
                'email' => $this->attemptedEmail($event->request->input('email')),
            ]),
        };
    }

    /**
     * @param  array<string, mixed>|null  $details
     */
    private function recordForUser(AuditEvent $auditEvent, mixed $user, ?array $details): void
    {
        $subject = $user instanceof Model ? $user : null;
        $userId = $subject?->getKey();

        $this->trail->record($auditEvent, $subject, null, $details, is_string($userId) ? $userId : null);
    }

    private function recordFailure(Failed $event): void
    {
        $subject = $event->user instanceof Model ? $event->user : null;

        // The actor of a failed login is unknown; the targeted account is the subject.
        $this->trail->record(AuditEvent::LoginFailed, $subject, null, [
            'email' => $this->attemptedEmail($event->credentials['email'] ?? null),
        ]);
    }

    private function attemptedEmail(mixed $email): ?string
    {
        return is_string($email) ? mb_substr(mb_strtolower(trim($email)), 0, 255) : null;
    }
}
