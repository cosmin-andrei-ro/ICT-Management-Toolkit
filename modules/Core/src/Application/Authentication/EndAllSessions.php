<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Authentication;

use Illuminate\Support\Str;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;

/**
 * Ends every session of a person, on any device, at their next request. Sessions
 * cannot be listed in every session store, so each is compared with this moment
 * (SetCurrentOrganization), and "keep me signed in" stops working too.
 */
final readonly class EndAllSessions
{
    public function __construct(private AuditTrail $trail) {}

    public function handle(User $user, string $reason): void
    {
        $user->forceFill(['sessions_revoked_at' => now(), 'remember_token' => Str::random(60)])->saveQuietly();
        $this->trail->record(AuditEvent::Logout, $user, null, ['reason' => $reason], $user->id);
    }
}
