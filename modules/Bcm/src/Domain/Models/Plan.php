<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Domain\Models;

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
use Modules\Bcm\Application\Policies\PlanPolicy;
use Modules\Bcm\Database\Factories\PlanFactory;
use Modules\Bcm\Domain\Enums\PlanType;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\User;

/**
 * A business continuity plan or an IT disaster recovery plan. Its content lives
 * in versions; the approved one is in force and is summed up here.
 *
 * @property string $id
 * @property string $organization_id
 * @property PlanType $type
 * @property string $name
 * @property ?string $description
 * @property ?string $owner_id
 * @property ?string $deputy_id
 * @property string $status active or retired
 * @property ?int $approved_version
 * @property ?Carbon $approved_at
 * @property ?CarbonImmutable $next_review_on
 * @property ?CarbonImmutable $last_exercised_on Last completed exercise.
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read ?User $owner
 * @property-read ?User $deputy
 */
#[Table('bcm_plans')]
#[UseFactory(PlanFactory::class)]
#[UsePolicy(PlanPolicy::class)]
#[Fillable(['type', 'name', 'description', 'owner_id', 'deputy_id', 'status'])]
final class Plan extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * The owner and the deputy keep the plan up to date.
     */
    public function isOwnedBy(User $user): bool
    {
        return $user->id === $this->owner_id || $user->id === $this->deputy_id;
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
     * @return HasMany<PlanVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(PlanVersion::class)->orderByDesc('version');
    }

    /**
     * @return HasMany<Exercise, $this>
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class)->orderByDesc('scheduled_on');
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
            'type' => PlanType::class,
            'approved_version' => 'integer',
            'approved_at' => 'datetime',
            'next_review_on' => 'immutable_date',
            'last_exercised_on' => 'immutable_date',
        ];
    }
}
