<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Sso;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Models\User;

/**
 * Which account a person of an identity provider ("sub") signs in to.
 *
 * @property string $id
 * @property string $provider_id
 * @property string $subject
 * @property string $user_id
 * @property ?Carbon $last_login_at
 * @property-read User $user
 */
#[Table('core_sso_identities')]
final class SsoIdentity extends Model
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
        return ['last_login_at' => 'datetime'];
    }
}
