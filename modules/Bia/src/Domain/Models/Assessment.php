<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Bia\Application\Policies\AssessmentPolicy;
use Modules\Bia\Database\Factories\AssessmentFactory;
use Modules\Bia\Domain\Enums\AssessmentStatus;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\User;

/**
 * One version of a process's impact analysis: the impact matrix and the
 * recovery objectives. Editable while draft or returned; approved and
 * superseded versions are history and never change. Written through the
 * assessment actions only.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $process_id
 * @property int $version
 * @property AssessmentStatus $status
 * @property ?int $unacceptable_level
 * @property ?int $mtpd_minutes
 * @property ?string $mtpd_reason
 * @property ?int $rto_minutes
 * @property ?int $rpo_minutes
 * @property ?string $mbco
 * @property ?string $workarounds
 * @property ?int $minimum_staff
 * @property ?string $notes
 * @property ?Carbon $submitted_at
 * @property ?string $submitted_by
 * @property ?Carbon $decided_at
 * @property ?string $decided_by
 * @property ?string $decision_comment
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read BusinessProcess $process
 * @property-read ?User $submitter
 * @property-read ?User $decider
 */
#[Table('bia_assessments')]
#[UseFactory(AssessmentFactory::class)]
#[UsePolicy(AssessmentPolicy::class)]
final class Assessment extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<AssessmentFactory> */
    use HasFactory;

    use HasUuids;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return BelongsTo<BusinessProcess, $this>
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(BusinessProcess::class, 'process_id')->withTrashed();
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
     * @return HasMany<ImpactScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(ImpactScore::class, 'assessment_id');
    }

    /**
     * Scores keyed "categoryId|horizonId".
     *
     * @return array<string, int>
     */
    public function scoreMap(): array
    {
        $map = [];

        foreach ($this->scores()->get() as $score) {
            $map[$score->category_id.'|'.$score->horizon_id] = $score->score;
        }

        // Rows come back in no set order; a stable order lets versions be compared.
        ksort($map);

        return $map;
    }

    protected function casts(): array
    {
        return [
            'status' => AssessmentStatus::class,
            'version' => 'integer',
            'unacceptable_level' => 'integer',
            'mtpd_minutes' => 'integer',
            'rto_minutes' => 'integer',
            'rpo_minutes' => 'integer',
            'minimum_staff' => 'integer',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }
}
