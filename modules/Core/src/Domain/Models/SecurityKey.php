<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A security key or passkey of a person (WebAuthn): its public key only,
 * written by SecurityKeys, which records additions and removals in the audit
 * trail.
 *
 * @property string $id
 * @property string $user_id
 * @property string $name
 * @property string $credential_id
 * @property array<string, mixed> $record
 * @property bool $is_passkey
 * @property ?Carbon $last_used_at
 * @property ?Carbon $created_at
 * @property-read User $user
 */
#[Table('core_security_keys')]
final class SecurityKey extends Model
{
    use HasUuids;

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
            'record' => 'array',
            'is_passkey' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }
}
