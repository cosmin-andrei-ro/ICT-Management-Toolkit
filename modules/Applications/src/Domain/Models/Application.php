<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Domain\Models;

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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Applications\Application\Policies\ApplicationPolicy;
use Modules\Applications\Database\Factories\ApplicationFactory;
use Modules\Applications\Domain\Enums\SheetStatus;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\HasCustomFields;
use Modules\Core\Domain\Concerns\HasTags;
use Modules\Core\Domain\Concerns\Taggable;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\Department;
use Modules\Core\Domain\Models\User;

/**
 * An application of the portfolio: its identity and owners. Its documentation
 * lives in sheet versions; the one in force is summed up here.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $code
 * @property string $name
 * @property ?string $department_id
 * @property ?string $department_email
 * @property ?string $business_owner_id
 * @property ?string $technical_owner_id
 * @property ?string $deputy_id
 * @property ?string $status Key of the application_status list.
 * @property ?string $criticality Key of the business_criticality list.
 * @property ?string $current_version
 * @property ?Carbon $approved_at
 * @property ?CarbonImmutable $next_review_on
 * @property ?string $configuration_item_id The application's own CMDB item.
 * @property ?CarbonImmutable $review_reminded_for
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read ?Department $department
 * @property-read ?User $businessOwner
 * @property-read ?User $technicalOwner
 * @property-read ?User $deputy
 */
#[Table('applications_applications')]
#[UseFactory(ApplicationFactory::class)]
#[UsePolicy(ApplicationPolicy::class)]
#[Fillable(['code', 'name', 'department_id', 'department_email', 'business_owner_id', 'technical_owner_id', 'deputy_id'])]
final class Application extends Model implements Taggable
{
    use Auditable;
    use BelongsToOrganization;
    use HasCustomFields;

    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    use HasTags;
    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    /**
     * The business owner, the technical owner and the deputy keep the sheet up to date.
     */
    public function isOwnedBy(User $user): bool
    {
        return in_array($user->id, [$this->business_owner_id, $this->technical_owner_id, $this->deputy_id], true);
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
    public function businessOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function technicalOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technical_owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deputy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deputy_id');
    }

    /**
     * The documentation version in force.
     *
     * @return HasOne<Sheet, $this>
     */
    public function sheetInForce(): HasOne
    {
        return $this->hasOne(Sheet::class)->where('status', SheetStatus::Approved);
    }

    /**
     * @return HasMany<Sheet, $this>
     */
    public function sheets(): HasMany
    {
        return $this->hasMany(Sheet::class)->orderByDesc('created_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->where(fn (Builder $inner) => $inner->where('business_owner_id', $user->id)->orWhere('technical_owner_id', $user->id)->orWhere('deputy_id', $user->id));
    }

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'next_review_on' => 'immutable_date',
            'review_reminded_for' => 'immutable_date',
        ];
    }
}
