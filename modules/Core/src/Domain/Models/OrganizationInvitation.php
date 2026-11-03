<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An invitation for an existing account to join another organization, waiting
 * for that person to accept it (InviteUser, AcceptOrganizationInvitation).
 * Not scoped to the current organization: the person sees the invitations of
 * every organization addressed to them.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $user_id
 * @property list<string> $role_ids
 * @property ?string $invited_by
 * @property Carbon $expires_at
 * @property ?Carbon $created_at
 * @property-read Organization $organization
 * @property-read User $user
 * @property-read ?User $inviter
 */
#[Table('core_organization_invitations')]
final class OrganizationInvitation extends Model
{
    use HasUuids;

    public const int DAYS_VALID = 14;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * @return Builder<self>
     */
    public static function pendingFor(User $user): Builder
    {
        return self::query()->where('user_id', $user->id)->where('expires_at', '>', now());
    }

    protected function casts(): array
    {
        return [
            'role_ids' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
