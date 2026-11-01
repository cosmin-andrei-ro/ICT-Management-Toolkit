<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Changes\ApprovalDecision;

/**
 * One CAB member's decision in one approval round. Rejections need a reason (CHECK).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $ticket_id
 * @property int $round
 * @property string $user_id
 * @property ApprovalDecision $decision
 * @property ?string $comment
 * @property Carbon $decided_at
 * @property-read User $user
 */
#[Table('ticketing_change_approvals')]
final class ChangeApproval extends Model
{
    use Auditable;
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

    protected function casts(): array
    {
        return [
            'decision' => ApprovalDecision::class,
            'round' => 'integer',
            'decided_at' => 'datetime',
        ];
    }
}
