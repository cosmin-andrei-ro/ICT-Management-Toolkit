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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Audit\Application\Policies\FindingPolicy;
use Modules\Audit\Database\Factories\FindingFactory;
use Modules\Audit\Domain\Enums\FindingSeverity;
use Modules\Audit\Domain\Enums\FindingStatus;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\User;

/**
 * Something an audit or a control test found: a nonconformity to correct,
 * an observation, an opportunity for improvement.
 *
 * @property string $id
 * @property string $organization_id
 * @property int $number
 * @property ?string $audit_id
 * @property ?string $test_id
 * @property ?string $control_id
 * @property ?string $requirement_id
 * @property string $title
 * @property string $description
 * @property FindingSeverity $severity
 * @property ?string $owner_id
 * @property ?CarbonImmutable $due_on
 * @property ?string $root_cause
 * @property FindingStatus $status
 * @property ?string $submitted_by
 * @property ?Carbon $submitted_at
 * @property ?string $verified_by
 * @property ?Carbon $closed_at
 * @property ?string $verification_notes
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read ?Audit $audit
 * @property-read ?Control $control
 * @property-read ?Requirement $requirement
 * @property-read ?User $owner
 * @property-read ?User $verifier
 */
#[Table('audit_findings')]
#[UseFactory(FindingFactory::class)]
#[UsePolicy(FindingPolicy::class)]
final class Finding extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<FindingFactory> */
    use HasFactory;

    use HasUuids;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'open',
    ];

    public function code(): string
    {
        return 'F-'.str_pad((string) $this->number, 3, '0', STR_PAD_LEFT);
    }

    public function isOverdue(CarbonImmutable $today): bool
    {
        return $this->status !== FindingStatus::Closed && $this->due_on !== null && $this->due_on->lt($today);
    }

    /**
     * @return BelongsTo<Audit, $this>
     */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    /**
     * @return BelongsTo<Control, $this>
     */
    public function control(): BelongsTo
    {
        return $this->belongsTo(Control::class);
    }

    /**
     * @return BelongsTo<Requirement, $this>
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
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
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return HasMany<FindingAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(FindingAction::class)->orderBy('created_at');
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
            'number' => 'integer',
            'severity' => FindingSeverity::class,
            'status' => FindingStatus::class,
            'due_on' => 'immutable_date',
            'submitted_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
