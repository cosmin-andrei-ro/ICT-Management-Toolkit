<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Models\User;
use Modules\Pam\Application\Policies\AccessRequestPolicy;
use Modules\Pam\Domain\Enums\RequestStatus;

/**
 * Someone asking to read a credential that needs approval. An approved request
 * works until it expires.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $account_id
 * @property string $user_id
 * @property string $reason
 * @property RequestStatus $status
 * @property ?string $decided_by
 * @property ?Carbon $decided_at
 * @property ?string $decision_comment
 * @property ?Carbon $expires_at
 * @property ?Carbon $created_at
 * @property-read PrivilegedAccount $account
 * @property-read User $user
 */
#[Table('pam_access_requests')]
#[UsePolicy(AccessRequestPolicy::class)]
final class AccessRequest extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;

    public function isActive(): bool
    {
        return $this->status === RequestStatus::Approved && $this->expires_at !== null && $this->expires_at->isFuture();
    }

    /**
     * @return BelongsTo<PrivilegedAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(PrivilegedAccount::class, 'account_id');
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
            'status' => RequestStatus::class,
            'decided_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
