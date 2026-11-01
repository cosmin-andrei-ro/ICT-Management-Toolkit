<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;

/**
 * Analysis of a problem ticket (1:1). Written only by the problem actions.
 *
 * @property string $ticket_id
 * @property string $organization_id
 * @property ?string $root_cause
 * @property ?string $workaround
 * @property ?Carbon $known_error_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
#[Table('ticketing_problem_details')]
final class ProblemDetails extends Model
{
    use Auditable;
    use BelongsToOrganization;

    public $incrementing = false;

    protected $primaryKey = 'ticket_id';

    protected $keyType = 'string';

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function isKnownError(): bool
    {
        return $this->known_error_at !== null;
    }

    protected function casts(): array
    {
        return ['known_error_at' => 'datetime'];
    }
}
