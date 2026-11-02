<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Domain\Models;

use Carbon\CarbonImmutable;
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
use Modules\Audit\Application\Policies\ControlPolicy;
use Modules\Audit\Database\Factories\ControlFactory;
use Modules\Audit\Domain\Enums\ControlStatus;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\User;

/**
 * One of the organization's own controls (e.g. "MFA on remote access"),
 * meeting requirements of any number of frameworks.
 *
 * @property string $id
 * @property string $organization_id
 * @property int $number
 * @property string $name
 * @property ?string $description
 * @property ?string $owner_id
 * @property ControlStatus $status
 * @property ?string $implementation_notes
 * @property ?int $test_interval_months
 * @property ?CarbonImmutable $last_tested_on
 * @property ?CarbonImmutable $next_test_on
 * @property ?CarbonImmutable $test_reminded_for
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read ?User $owner
 */
#[Table('audit_controls')]
#[UseFactory(ControlFactory::class)]
#[UsePolicy(ControlPolicy::class)]
final class Control extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<ControlFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'planned',
    ];

    public function code(): string
    {
        return 'C-'.str_pad((string) $this->number, 3, '0', STR_PAD_LEFT);
    }

    public function isTestOverdue(CarbonImmutable $today): bool
    {
        return $this->status !== ControlStatus::Retired && $this->next_test_on !== null && $this->next_test_on->lt($today);
    }

    /**
     * @return HasMany<Evidence, $this>
     */
    public function evidence(): HasMany
    {
        return $this->hasMany(Evidence::class)->latest();
    }

    /**
     * @return HasMany<ControlTest, $this>
     */
    public function tests(): HasMany
    {
        return $this->hasMany(ControlTest::class)->orderByDesc('tested_on')->orderByDesc('created_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<Requirement, $this>
     */
    public function requirements(): BelongsToMany
    {
        return $this->belongsToMany(Requirement::class, 'audit_control_requirement', 'control_id', 'requirement_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->where('owner_id', $user->id);
    }

    protected function casts(): array
    {
        return [
            'status' => ControlStatus::class,
            'number' => 'integer',
            'test_interval_months' => 'integer',
            'last_tested_on' => 'immutable_date',
            'next_test_on' => 'immutable_date',
            'test_reminded_for' => 'immutable_date',
        ];
    }
}
