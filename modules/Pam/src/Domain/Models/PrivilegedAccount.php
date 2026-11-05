<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\User;
use Modules\Pam\Application\Policies\PrivilegedAccountPolicy;
use Modules\Pam\Database\Factories\PrivilegedAccountFactory;
use Modules\Pam\Domain\Enums\AccountType;

/**
 * A privileged account and its credential (ADR 0078). The credential is read and
 * written only through PamVault; it is hidden from serialization and left out of
 * the audit trail, which records that it was shown or changed, never its value.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string $username
 * @property AccountType $type
 * @property ?string $description
 * @property ?string $configuration_item_id
 * @property ?string $application_id
 * @property ?string $owner_id
 * @property ?string $secret_ciphertext
 * @property ?Carbon $secret_changed_at
 * @property bool $requires_approval
 * @property ?string $approver_id
 * @property int $access_minutes
 * @property ?int $rotation_days
 * @property ?Carbon $rotation_reminded_at
 * @property ?int $review_days
 * @property ?Carbon $reviewed_at
 * @property ?string $reviewed_by
 * @property ?Carbon $review_reminded_at
 * @property ?Carbon $created_at
 * @property-read ?User $owner
 */
#[Table('pam_accounts')]
#[UseFactory(PrivilegedAccountFactory::class)]
#[UsePolicy(PrivilegedAccountPolicy::class)]
#[Hidden(['secret_ciphertext'])]
#[Fillable(['name', 'username', 'type', 'description', 'configuration_item_id', 'application_id', 'owner_id', 'requires_approval', 'approver_id', 'access_minutes', 'rotation_days', 'review_days'])]
final class PrivilegedAccount extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = ['requires_approval' => false, 'access_minutes' => 60];

    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<PrivilegedAccountFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    /**
     * @return list<string>
     */
    public function auditExclude(): array
    {
        return ['secret_ciphertext', 'secret_changed_at', 'rotation_reminded_at', 'review_reminded_at'];
    }

    /**
     * When the credential should be replaced; null without a schedule or a credential.
     */
    public function rotationDueOn(): ?Carbon
    {
        return $this->rotation_days !== null && $this->secret_changed_at !== null ? $this->secret_changed_at->copy()->addDays($this->rotation_days) : null;
    }

    /**
     * When someone should confirm who has access; null without a schedule.
     */
    public function reviewDueOn(): ?Carbon
    {
        $since = $this->reviewed_at ?? $this->created_at;

        return $this->review_days !== null && $since !== null ? $since->copy()->addDays($this->review_days) : null;
    }

    public function hasSecret(): bool
    {
        return $this->secret_ciphertext !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<AccountGrant, $this>
     */
    public function grants(): HasMany
    {
        return $this->hasMany(AccountGrant::class, 'account_id');
    }

    /**
     * @return HasMany<AccessRequest, $this>
     */
    public function accessRequests(): HasMany
    {
        return $this->hasMany(AccessRequest::class, 'account_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function grantedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pam_account_grants', 'account_id', 'user_id');
    }

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'secret_changed_at' => 'datetime',
            'requires_approval' => 'boolean',
            'access_minutes' => 'integer',
            'rotation_days' => 'integer',
            'rotation_reminded_at' => 'datetime',
            'review_days' => 'integer',
            'reviewed_at' => 'datetime',
            'review_reminded_at' => 'datetime',
        ];
    }
}
