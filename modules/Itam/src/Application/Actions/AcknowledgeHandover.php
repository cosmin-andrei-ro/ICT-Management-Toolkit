<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;
use Modules\Itam\Domain\Models\AssetAssignment;

/**
 * The holder confirms they received an asset. Only the holder, only for an
 * assignment still open, and once: the first confirmation stands. Recorded on
 * the asset's audit trail and printed on the handover protocol.
 */
final readonly class AcknowledgeHandover
{
    public function __construct(
        private ConnectionInterface $db,
        private AuditTrail $trail,
    ) {}

    /**
     * @throws AuthorizationException when the assignment is not the person's own open one
     */
    public function handle(AssetAssignment $assignment, User $holder, ?string $ip): void
    {
        $this->db->transaction(function () use ($assignment, $holder, $ip): void {
            $assignment = AssetAssignment::query()->with('asset')->lockForUpdate()->findOrFail($assignment->id);

            if ($assignment->user_id !== $holder->id || $assignment->returned_at !== null) {
                throw new AuthorizationException;
            }

            if ($assignment->acknowledged_at !== null) {
                return;
            }

            $assignment->forceFill(['acknowledged_at' => now(), 'acknowledged_ip' => mb_substr($ip ?? 'unknown', 0, 45)])->save();
            $this->trail->record(AuditEvent::Updated, $assignment->asset, null, ['handover_acknowledged_by' => $holder->name], $holder->id);
        });
    }
}
