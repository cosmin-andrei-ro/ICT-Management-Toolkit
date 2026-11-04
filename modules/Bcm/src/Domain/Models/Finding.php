<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Bcm\Domain\Enums\FindingSeverity;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Models\User;

/**
 * Something an exercise showed must be fixed. It can become a problem in the
 * ticketing, which then tracks the fix.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $exercise_id
 * @property ?string $exercise_step_id
 * @property string $description
 * @property FindingSeverity $severity
 * @property ?string $ticket_id
 * @property ?string $ticket_number
 * @property ?string $created_by
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read ?ExerciseStep $step
 * @property-read ?User $author
 */
#[Table('bcm_exercise_findings')]
final class Finding extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    /**
     * @return BelongsTo<ExerciseStep, $this>
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(ExerciseStep::class, 'exercise_step_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'severity' => FindingSeverity::class,
        ];
    }
}
