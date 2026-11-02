<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Models\User;
use Modules\Risk\Domain\Enums\RiskCategory;
use Modules\Risk\Domain\Enums\SuggestionStatus;

/**
 * A risk another module suggests. Written by the suggestion actions; decisions
 * are audited.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $key
 * @property string $source
 * @property string $source_label
 * @property string $title
 * @property string $description
 * @property RiskCategory $category
 * @property list<array{type: string, id: string}> $assets
 * @property list<string> $threats
 * @property list<string> $vulnerabilities
 * @property ?int $likelihood
 * @property ?int $impact
 * @property ?string $evidence_url
 * @property ?string $owner_id
 * @property SuggestionStatus $status
 * @property ?string $risk_id
 * @property ?string $dismissal_reason
 * @property ?string $decided_by
 * @property ?Carbon $decided_at
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 * @property-read ?User $decider
 * @property-read ?Risk $risk
 */
#[Table('risk_suggestions')]
final class Suggestion extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;

    public $timestamps = false;

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return BelongsTo<Risk, $this>
     */
    public function risk(): BelongsTo
    {
        return $this->belongsTo(Risk::class)->withTrashed();
    }

    protected function casts(): array
    {
        return [
            'category' => RiskCategory::class,
            'status' => SuggestionStatus::class,
            'assets' => 'array',
            'threats' => 'array',
            'vulnerabilities' => 'array',
            'likelihood' => 'integer',
            'impact' => 'integer',
            'decided_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
