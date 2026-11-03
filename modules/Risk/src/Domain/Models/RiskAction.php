<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\User;
use Modules\Risk\Domain\Enums\ActionStatus;

/**
 * Something to do to treat a risk. Written by ManageRiskActions, which audits
 * it on the risk.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $risk_id
 * @property string $title
 * @property ?string $owner_id
 * @property ?CarbonImmutable $due_on
 * @property ActionStatus $status
 * @property ?Carbon $completed_at
 * @property ?string $ticket_id
 * @property ?string $ticket_number
 * @property ?Carbon $created_at
 * @property-read ?Risk $risk
 * @property-read ?User $owner
 */
#[Table('risk_actions')]
final class RiskAction extends Model
{
    use BelongsToOrganization;
    use HasUuids;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'planned',
    ];

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->due_on !== null && $this->due_on->isPast() && ! $this->due_on->isToday();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Risk, $this>
     */
    public function risk(): BelongsTo
    {
        return $this->belongsTo(Risk::class);
    }

    protected function casts(): array
    {
        return [
            'status' => ActionStatus::class,
            'due_on' => 'immutable_date',
            'completed_at' => 'datetime',
        ];
    }
}
