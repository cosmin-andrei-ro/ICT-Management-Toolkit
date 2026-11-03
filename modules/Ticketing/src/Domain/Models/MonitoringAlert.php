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
use Modules\Core\Domain\Concerns\BelongsToOrganization;

/**
 * A monitoring alert and the incident it opened (ADR 0067). Firing until the
 * tool reports it cleared, or until its incident is no longer open.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $key
 * @property ?string $source
 * @property string $status firing or resolved
 * @property string $ticket_id
 * @property ?string $configuration_item_id
 * @property int $occurrences
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 * @property ?Carbon $resolved_at
 * @property-read Ticket $ticket
 */
#[Table('ticketing_monitoring_alerts')]
final class MonitoringAlert extends Model
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

    protected function casts(): array
    {
        return [
            'occurrences' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
