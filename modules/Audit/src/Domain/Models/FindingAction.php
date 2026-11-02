<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Audit\Domain\Enums\ActionStatus;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\User;

/**
 * A corrective action of a finding. Written by ManageFindingActions, which
 * audits it on the finding.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $finding_id
 * @property string $title
 * @property ?string $owner_id
 * @property ?CarbonImmutable $due_on
 * @property ActionStatus $status
 * @property ?Carbon $completed_at
 * @property ?string $ticket_id
 * @property ?string $ticket_number
 * @property ?Carbon $created_at
 * @property-read ?User $owner
 */
#[Table('audit_finding_actions')]
final class FindingAction extends Model
{
    use BelongsToOrganization;
    use HasUuids;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'planned',
    ];

    public function isOverdue(CarbonImmutable $today): bool
    {
        return $this->status->isOpen() && $this->due_on !== null && $this->due_on->lt($today);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
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
