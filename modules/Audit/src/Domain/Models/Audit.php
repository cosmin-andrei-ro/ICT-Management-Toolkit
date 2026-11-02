<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Audit\Application\Policies\AuditPolicy;
use Modules\Audit\Database\Factories\AuditFactory;
use Modules\Audit\Domain\Enums\AuditKind;
use Modules\Audit\Domain\Enums\AuditStatus;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\User;

/**
 * An internal or external audit: its scope, the frameworks it covers, when
 * it runs, and its findings.
 *
 * @property string $id
 * @property string $organization_id
 * @property int $number
 * @property string $title
 * @property AuditKind $kind
 * @property ?string $scope
 * @property ?string $lead_auditor_id
 * @property ?string $audit_body
 * @property CarbonImmutable $starts_on
 * @property ?CarbonImmutable $ends_on
 * @property AuditStatus $status
 * @property ?string $conclusion
 * @property ?Carbon $completed_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read ?User $leadAuditor
 */
#[Table('audit_audits')]
#[UseFactory(AuditFactory::class)]
#[UsePolicy(AuditPolicy::class)]
final class Audit extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<AuditFactory> */
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
        return 'AUD-'.str_pad((string) $this->number, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function leadAuditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_auditor_id');
    }

    /**
     * @return BelongsToMany<Framework, $this>
     */
    public function frameworks(): BelongsToMany
    {
        return $this->belongsToMany(Framework::class, 'audit_audit_framework', 'audit_id', 'framework_id')->orderBy('name');
    }

    /**
     * @return HasMany<Finding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class)->orderBy('number');
    }

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'kind' => AuditKind::class,
            'status' => AuditStatus::class,
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'completed_at' => 'datetime',
        ];
    }
}
