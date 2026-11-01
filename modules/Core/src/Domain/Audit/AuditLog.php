<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Audit;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Models\User;

/**
 * Append-only: the database rejects UPDATE, DELETE and TRUNCATE (ADR 0005).
 * Not organization-scoped by a global scope, because entries for global entities
 * (users, organizations) have no organization; readers must filter explicitly.
 *
 * @property string $id
 * @property ?string $organization_id
 * @property ?string $user_id
 * @property AuditEvent $event
 * @property string $auditable_type
 * @property string $auditable_id
 * @property ?array<string, mixed> $old_values
 * @property ?array<string, mixed> $new_values
 * @property ?string $ip_address
 * @property ?string $user_agent
 * @property ?string $request_id
 * @property Carbon $created_at
 */
#[Table('core_audit_logs')]
final class AuditLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
