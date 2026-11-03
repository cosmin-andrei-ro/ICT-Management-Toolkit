<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\User;
use Modules\Projects\Application\Policies\ProjectPolicy;
use Modules\Projects\Database\Factories\ProjectFactory;
use Modules\Projects\Domain\Enums\PhaseStatus;
use Modules\Projects\Domain\Enums\ProjectKind;
use Modules\Projects\Domain\Enums\ProjectStatus;

/**
 * An IT project: its sponsor, manager and team, four phases and their tasks.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $code
 * @property string $name
 * @property ?string $description
 * @property ProjectKind $kind
 * @property ?string $application_id
 * @property ProjectStatus $status
 * @property ?string $sponsor_id
 * @property ?string $manager_id
 * @property ?Carbon $planned_start
 * @property ?Carbon $planned_end
 * @property ?string $budget_amount
 * @property ?string $currency
 * @property ?Carbon $closed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ?User $sponsor
 * @property-read ?User $manager
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $members
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Phase> $phases
 */
#[Table('projects_projects')]
#[UseFactory(ProjectFactory::class)]
#[UsePolicy(ProjectPolicy::class)]
final class Project extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    protected $attributes = [
        'kind' => 'general',
        'status' => 'active',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'projects_members', 'project_id', 'user_id')->withPivot('role');
    }

    /**
     * @return HasMany<Phase, $this>
     */
    public function phases(): HasMany
    {
        return $this->hasMany(Phase::class)->orderBy('position');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<Cost, $this>
     */
    public function costs(): HasMany
    {
        return $this->hasMany(Cost::class);
    }

    /**
     * What has been spent so far, in the project's currency.
     */
    public function spent(): float
    {
        return (float) $this->costs()->sum('amount');
    }

    /**
     * @return HasMany<Deliverable, $this>
     */
    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class);
    }

    /**
     * @return HasMany<GateRequest, $this>
     */
    public function gateRequests(): HasMany
    {
        return $this->hasMany(GateRequest::class)->latest();
    }

    /**
     * @return HasMany<StatusReport, $this>
     */
    public function statusReports(): HasMany
    {
        return $this->hasMany(StatusReport::class)->orderByDesc('reported_on');
    }

    public function pendingGate(): ?GateRequest
    {
        return $this->gateRequests()->where('status', 'pending')->first();
    }

    public function latestReport(): ?StatusReport
    {
        return $this->statusReports()->first();
    }

    public function currentPhase(): ?Phase
    {
        return $this->phases->firstWhere('status', PhaseStatus::Active);
    }

    /**
     * Its manager, sponsor or a team member.
     */
    public function involves(User $user): bool
    {
        return $this->manager_id === $user->id || $this->sponsor_id === $user->id
            || $this->members()->whereKey($user->id)->exists();
    }

    /**
     * Projects a person runs, sponsors or works on.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeInvolving(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $involved) => $involved->where('manager_id', $user->id)->orWhere('sponsor_id', $user->id)
            ->orWhereHas('members', fn (Builder $members) => $members->whereKey($user->id)));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [ProjectStatus::Active, ProjectStatus::OnHold], true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ProjectKind::class,
            'status' => ProjectStatus::class,
            'planned_start' => 'date',
            'planned_end' => 'date',
            'closed_at' => 'datetime',
        ];
    }
}
