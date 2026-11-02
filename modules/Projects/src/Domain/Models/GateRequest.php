<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Models\User;
use Modules\Projects\Domain\Enums\GateStatus;

/**
 * A request to move a project to its next phase, or to close it.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $project_id
 * @property string $from_phase_id
 * @property ?string $to_phase_id
 * @property ?string $note
 * @property GateStatus $status
 * @property ?string $requested_by
 * @property ?string $decided_by
 * @property ?Carbon $decided_at
 * @property ?string $decision_note
 * @property Carbon $created_at
 * @property-read Project $project
 * @property-read Phase $fromPhase
 * @property-read ?Phase $toPhase
 * @property-read ?User $requester
 * @property-read ?User $decider
 */
#[Table('projects_gate_requests')]
final class GateRequest extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;

    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Phase, $this>
     */
    public function fromPhase(): BelongsTo
    {
        return $this->belongsTo(Phase::class, 'from_phase_id');
    }

    /**
     * @return BelongsTo<Phase, $this>
     */
    public function toPhase(): BelongsTo
    {
        return $this->belongsTo(Phase::class, 'to_phase_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function closesProject(): bool
    {
        return $this->to_phase_id === null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GateStatus::class,
            'decided_at' => 'datetime',
        ];
    }
}
