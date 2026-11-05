<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Domain\Models;

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
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\HasCustomFields;
use Modules\Core\Domain\Concerns\HasTags;
use Modules\Core\Domain\Concerns\Taggable;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\User;
use Modules\Risk\Application\Policies\RiskPolicy;
use Modules\Risk\Database\Factories\RiskFactory;
use Modules\Risk\Domain\Enums\RiskCategory;
use Modules\Risk\Domain\Enums\RiskStatus;
use Modules\Risk\Domain\Enums\Treatment;

/**
 * A risk scenario of the register: what could happen, to what, how likely and
 * how bad before treatment (inherent risk). Written through the risk actions.
 *
 * @property string $id
 * @property string $organization_id
 * @property int $reference
 * @property string $title
 * @property ?string $description
 * @property RiskCategory $category
 * @property ?string $owner_id
 * @property RiskStatus $status
 * @property ?int $inherent_likelihood
 * @property ?int $inherent_impact
 * @property ?string $existing_controls
 * @property ?CarbonImmutable $next_review_on
 * @property ?Treatment $treatment
 * @property ?string $treatment_plan
 * @property ?int $residual_likelihood
 * @property ?int $residual_impact
 * @property ?Carbon $assessed_at
 * @property ?CarbonImmutable $accepted_until
 * @property ?CarbonImmutable $review_reminded_for
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read ?User $owner
 */
#[Table('risk_risks')]
#[UseFactory(RiskFactory::class)]
#[UsePolicy(RiskPolicy::class)]
final class Risk extends Model implements Taggable
{
    use Auditable;
    use BelongsToOrganization;
    use HasCustomFields;

    /** @use HasFactory<RiskFactory> */
    use HasFactory;

    use HasTags;
    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'open',
    ];

    public function code(): string
    {
        return 'R-'.str_pad((string) $this->reference, 4, '0', STR_PAD_LEFT);
    }

    public function inherentScore(): ?int
    {
        return $this->inherent_likelihood !== null && $this->inherent_impact !== null ? $this->inherent_likelihood * $this->inherent_impact : null;
    }

    public function residualScore(): ?int
    {
        return $this->residual_likelihood !== null && $this->residual_impact !== null ? $this->residual_likelihood * $this->residual_impact : null;
    }

    /**
     * The residual score when assessed, else the inherent one: what the risk is now.
     */
    public function currentScore(): ?int
    {
        return $this->residualScore() ?? $this->inherentScore();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_until !== null && ! $this->accepted_until->isPast();
    }

    /**
     * @return HasMany<RiskAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(RiskAction::class)->orderByRaw('due_on NULLS LAST')->orderBy('created_at');
    }

    /**
     * @return HasMany<Acceptance, $this>
     */
    public function acceptances(): HasMany
    {
        return $this->hasMany(Acceptance::class)->orderByDesc('requested_at');
    }

    /**
     * @return HasMany<ScoreRecord, $this>
     */
    public function scoreHistory(): HasMany
    {
        return $this->hasMany(ScoreRecord::class)->orderByDesc('recorded_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<CatalogEntry, $this>
     */
    public function catalogEntries(): BelongsToMany
    {
        return $this->belongsToMany(CatalogEntry::class, 'risk_risk_catalog', 'risk_id', 'entry_id');
    }

    /**
     * @return list<array{type: string, id: string}>
     */
    public function assets(): array
    {
        $assets = [];

        foreach ($this->getConnection()->table('risk_risk_assets')->where('risk_id', $this->id)->get(['asset_type', 'asset_id']) as $row) {
            if (is_string($row->asset_type) && is_string($row->asset_id)) {
                $assets[] = ['type' => $row->asset_type, 'id' => $row->asset_id];
            }
        }

        return $assets;
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
            'category' => RiskCategory::class,
            'status' => RiskStatus::class,
            'reference' => 'integer',
            'inherent_likelihood' => 'integer',
            'inherent_impact' => 'integer',
            'next_review_on' => 'immutable_date',
            'treatment' => Treatment::class,
            'residual_likelihood' => 'integer',
            'residual_impact' => 'integer',
            'assessed_at' => 'datetime',
            'accepted_until' => 'immutable_date',
            'review_reminded_for' => 'immutable_date',
        ];
    }
}
