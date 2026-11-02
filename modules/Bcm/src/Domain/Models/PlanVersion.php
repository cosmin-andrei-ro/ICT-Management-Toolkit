<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Bcm\Application\Policies\PlanVersionPolicy;
use Modules\Bcm\Database\Factories\PlanVersionFactory;
use Modules\Bcm\Domain\Enums\VersionStatus;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\User;

/**
 * One version of a plan: what it covers, who acts and what they do. Editable
 * while draft or returned; approved and superseded versions never change.
 * Written through the plan-version actions only.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $plan_id
 * @property int $version
 * @property VersionStatus $status
 * @property ?string $objective
 * @property list<string> $scenarios
 * @property ?string $activation_criteria
 * @property ?string $activation_authority
 * @property ?string $communication
 * @property ?string $strategy
 * @property ?string $assumptions
 * @property ?Carbon $submitted_at
 * @property ?string $submitted_by
 * @property ?Carbon $decided_at
 * @property ?string $decided_by
 * @property ?string $decision_comment
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read Plan $plan
 * @property-read ?User $submitter
 * @property-read ?User $decider
 */
#[Table('bcm_plan_versions')]
#[UseFactory(PlanVersionFactory::class)]
#[UsePolicy(PlanVersionPolicy::class)]
final class PlanVersion extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<PlanVersionFactory> */
    use HasFactory;

    use HasUuids;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'draft',
        'scenarios' => '[]',
    ];

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return HasMany<TeamMember, $this>
     */
    public function team(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'version_id')->orderBy('position');
    }

    /**
     * @return HasMany<Step, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(Step::class, 'version_id')->orderBy('position');
    }

    /**
     * Ids of the BIA processes the version covers.
     *
     * @return list<string>
     */
    public function processIds(): array
    {
        return array_values(array_filter(
            $this->getConnection()->table('bcm_version_processes')->where('version_id', $this->id)->pluck('process_id')->all(),
            is_string(...),
        ));
    }

    /**
     * Minutes from activation to service back: the activation and recovery
     * steps, one after the other. Null while no such step has an estimate.
     */
    public function estimatedRecoveryMinutes(): ?int
    {
        $minutes = $this->steps()->whereIn('phase', ['activation', 'recovery'])->whereNotNull('estimated_minutes')->sum('estimated_minutes');

        return (int) $minutes > 0 ? (int) $minutes : null;
    }

    protected function casts(): array
    {
        return [
            'status' => VersionStatus::class,
            'version' => 'integer',
            'scenarios' => 'array',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }
}
