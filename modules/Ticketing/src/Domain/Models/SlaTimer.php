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
use Modules\Ticketing\Domain\Sla\SlaMetric;
use Modules\Ticketing\Domain\Sla\SlaOutcome;

/**
 * Written only by SlaClock. Not audited: every change follows a ticket change that is.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $ticket_id
 * @property SlaMetric $metric
 * @property int $target_minutes
 * @property int $consumed_minutes
 * @property ?CarbonInterface $running_since
 * @property ?CarbonInterface $due_at
 * @property ?CarbonInterface $warn_at
 * @property ?CarbonInterface $warned_at
 * @property ?CarbonInterface $breached_at
 * @property ?CarbonInterface $completed_at
 * @property ?SlaOutcome $outcome
 * @property ?CarbonInterface $escalated_at
 * @property-read Ticket $ticket
 */
#[Table('ticketing_sla_timers')]
final class SlaTimer extends Model
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

    public function isRunning(): bool
    {
        return $this->running_since !== null;
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isBreached(): bool
    {
        return $this->breached_at !== null || $this->outcome === SlaOutcome::Breached;
    }

    protected function casts(): array
    {
        return [
            'metric' => SlaMetric::class,
            'outcome' => SlaOutcome::class,
            'target_minutes' => 'integer',
            'consumed_minutes' => 'integer',
            'running_since' => 'datetime',
            'due_at' => 'datetime',
            'warn_at' => 'datetime',
            'warned_at' => 'datetime',
            'breached_at' => 'datetime',
            'completed_at' => 'datetime',
            'escalated_at' => 'datetime',
        ];
    }
}
