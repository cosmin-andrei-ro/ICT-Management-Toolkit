<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Ticketing\Domain\Changes\ApprovalStatus;
use Modules\Ticketing\Domain\Changes\ChangeRisk;
use Modules\Ticketing\Domain\Changes\ChangeType;

/**
 * Plans, window and authorization of a change ticket (1:1). Written only by the change actions.
 *
 * @property string $ticket_id
 * @property string $organization_id
 * @property ChangeType $change_type
 * @property ChangeRisk $risk
 * @property ?string $cab_board_id
 * @property ?string $template_id
 * @property ?string $implementation_plan
 * @property ?string $rollback_plan
 * @property ?string $test_plan
 * @property ?CarbonInterface $planned_start
 * @property ?CarbonInterface $planned_end
 * @property ApprovalStatus $approval_status
 * @property int $approval_round
 * @property ?CarbonInterface $approved_at
 * @property ?string $review
 * @property ?CarbonInterface $reviewed_at
 * @property-read ?CabBoard $cabBoard
 * @property-read ?ChangeTemplate $template
 */
#[Table('ticketing_change_details')]
final class ChangeDetails extends Model
{
    use Auditable;
    use BelongsToOrganization;

    public $incrementing = false;

    protected $primaryKey = 'ticket_id';

    protected $keyType = 'string';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'approval_status' => 'draft',
        'approval_round' => 0,
    ];

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<CabBoard, $this>
     */
    public function cabBoard(): BelongsTo
    {
        return $this->belongsTo(CabBoard::class);
    }

    /**
     * @return BelongsTo<ChangeTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ChangeTemplate::class);
    }

    /**
     * Emergency changes close only after their post-implementation review.
     */
    public function canClose(): bool
    {
        return $this->change_type !== ChangeType::Emergency || $this->reviewed_at !== null;
    }

    public function isApproved(): bool
    {
        return $this->approval_status === ApprovalStatus::Approved;
    }

    protected function casts(): array
    {
        return [
            'change_type' => ChangeType::class,
            'risk' => ChangeRisk::class,
            'approval_status' => ApprovalStatus::class,
            'approval_round' => 'integer',
            'planned_start' => 'datetime',
            'planned_end' => 'datetime',
            'approved_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }
}
