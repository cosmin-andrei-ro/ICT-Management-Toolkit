<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Applications\Domain\Enums\ApprovalRole;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Models\User;

/**
 * One of the three approvals of a major version. Recorded by ApproveSheet,
 * which audits it on the version.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $sheet_id
 * @property ApprovalRole $role
 * @property ?string $user_id
 * @property ?string $comment
 * @property Carbon $approved_at
 * @property-read ?Sheet $sheet
 * @property-read ?User $user
 */
#[Table('applications_sheet_approvals')]
final class SheetApproval extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    public $timestamps = false;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Sheet, $this>
     */
    public function sheet(): BelongsTo
    {
        return $this->belongsTo(Sheet::class);
    }

    protected function casts(): array
    {
        return [
            'role' => ApprovalRole::class,
            'approved_at' => 'datetime',
        ];
    }
}
