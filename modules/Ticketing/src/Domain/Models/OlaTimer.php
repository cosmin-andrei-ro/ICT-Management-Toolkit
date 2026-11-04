<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Models\Team;
use Modules\Ticketing\Domain\Sla\SlaOutcome;

/**
 * The time a ticket has spent with one team, in working time (ADR 0074). Apart from
 * the SLA clocks: a promise between teams inside IT, not to the requester.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $ticket_id
 * @property ?string $team_id
 * @property int $target_minutes
 * @property int $consumed_minutes
 * @property ?CarbonInterface $running_since
 * @property ?CarbonInterface $due_at
 * @property ?CarbonInterface $breached_at
 * @property ?CarbonInterface $escalated_at
 * @property ?CarbonInterface $completed_at
 * @property ?SlaOutcome $outcome
 * @property-read Ticket $ticket
 * @property-read ?Team $team
 * @property-read null $warned_at
 */
#[Table('ticketing_ola_timers')]
final class OlaTimer extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isRunning(): bool
    {
        return $this->running_since !== null;
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * OLAs have no early warning. The SLA badge asks for one.
     */
    public function getWarnedAtAttribute(): null
    {
        return null;
    }

    protected function casts(): array
    {
        return [
            'outcome' => SlaOutcome::class,
            'target_minutes' => 'integer',
            'consumed_minutes' => 'integer',
            'running_since' => 'datetime',
            'due_at' => 'datetime',
            'breached_at' => 'datetime',
            'escalated_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
