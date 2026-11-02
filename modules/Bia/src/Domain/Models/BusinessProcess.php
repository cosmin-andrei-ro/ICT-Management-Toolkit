<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Bia\Application\Policies\BusinessProcessPolicy;
use Modules\Bia\Database\Factories\BusinessProcessFactory;
use Modules\Bia\Domain\Enums\Tier;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\Department;
use Modules\Core\Domain\Models\User;

/**
 * Something the business does that IT supports: payroll, invoicing, customer
 * support. Its recovery objectives come from its approved assessment and are
 * copied here (ApproveAssessment) for lists, reports and the gap analysis.
 *
 * @property string $id
 * @property string $organization_id
 * @property ?string $department_id
 * @property string $name
 * @property ?string $description
 * @property ?string $owner_id
 * @property ?string $deputy_id
 * @property ?string $peak_periods
 * @property string $status active or retired
 * @property ?int $mtpd_minutes
 * @property ?int $rto_minutes
 * @property ?int $rpo_minutes
 * @property ?Tier $tier
 * @property ?Carbon $approved_at
 * @property ?CarbonImmutable $next_review_on
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read ?Department $department
 * @property-read ?User $owner
 * @property-read ?User $deputy
 */
#[Table('bia_processes')]
#[UseFactory(BusinessProcessFactory::class)]
#[UsePolicy(BusinessProcessPolicy::class)]
#[Fillable(['department_id', 'name', 'description', 'owner_id', 'deputy_id', 'peak_periods', 'status'])]
final class BusinessProcess extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<BusinessProcessFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * The owner and the deputy fill in the process's assessments.
     */
    public function isOwnedBy(User $user): bool
    {
        return $user->id === $this->owner_id || $user->id === $this->deputy_id;
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deputy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deputy_id');
    }

    /**
     * @return HasMany<Assessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'process_id')->orderByDesc('version');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->where(fn (Builder $inner) => $inner->where('owner_id', $user->id)->orWhere('deputy_id', $user->id));
    }

    protected function casts(): array
    {
        return [
            'mtpd_minutes' => 'integer',
            'rto_minutes' => 'integer',
            'rpo_minutes' => 'integer',
            'tier' => Tier::class,
            'approved_at' => 'datetime',
            'next_review_on' => 'immutable_date',
        ];
    }
}
