<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Bcm\Application\Policies\ExercisePolicy;
use Modules\Bcm\Database\Factories\ExerciseFactory;
use Modules\Bcm\Domain\Enums\ExerciseOutcome;
use Modules\Bcm\Domain\Enums\ExerciseStatus;
use Modules\Bcm\Domain\Enums\ExerciseType;
use Modules\Bcm\Domain\Enums\Scenario;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\User;

/**
 * An exercise or recovery test of a plan in force. Once completed it is the
 * evidence of the test and never changes; only problems can still be raised
 * from its findings. Written through the exercise actions only.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $plan_id
 * @property ?string $version_id
 * @property ExerciseType $type
 * @property ?Scenario $scenario
 * @property string $title
 * @property CarbonImmutable $scheduled_on
 * @property ExerciseStatus $status
 * @property ?string $facilitator_id
 * @property ?string $participants
 * @property ?Carbon $started_at
 * @property ?Carbon $completed_at
 * @property ?ExerciseOutcome $outcome
 * @property ?int $measured_minutes
 * @property ?int $rto_minutes
 * @property ?string $summary
 * @property ?string $lessons
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read Plan $plan
 * @property-read ?PlanVersion $version
 * @property-read ?User $facilitator
 */
#[Table('bcm_exercises')]
#[UseFactory(ExerciseFactory::class)]
#[UsePolicy(ExercisePolicy::class)]
final class Exercise extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<ExerciseFactory> */
    use HasFactory;

    use HasUuids;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'planned',
    ];

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class)->withTrashed();
    }

    /**
     * @return BelongsTo<PlanVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function facilitator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'facilitator_id');
    }

    /**
     * @return HasMany<ExerciseStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ExerciseStep::class)->orderBy('position');
    }

    /**
     * @return HasMany<ExerciseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ExerciseItem::class);
    }

    /**
     * @return HasMany<Finding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class)->orderBy('created_at');
    }

    /**
     * Minutes from activation to service back, as measured: the activation and
     * recovery steps that were carried out. Null while none has a time.
     */
    public function measuredRecoveryMinutes(): ?int
    {
        $minutes = $this->steps()->whereIn('phase', ['activation', 'recovery'])->whereNotNull('actual_minutes')->sum('actual_minutes');

        return (int) $minutes > 0 ? (int) $minutes : null;
    }

    protected function casts(): array
    {
        return [
            'type' => ExerciseType::class,
            'scenario' => Scenario::class,
            'status' => ExerciseStatus::class,
            'outcome' => ExerciseOutcome::class,
            'scheduled_on' => 'immutable_date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'measured_minutes' => 'integer',
            'rto_minutes' => 'integer',
        ];
    }
}
