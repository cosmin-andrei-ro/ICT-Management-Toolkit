<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Models\User;
use Modules\Risk\Domain\Enums\AcceptanceStatus;

/**
 * A request to accept a risk's residual level, with its justification, and
 * its decision. Written by AcceptRisk, which audits it on the risk.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $risk_id
 * @property int $likelihood
 * @property int $impact
 * @property string $justification
 * @property AcceptanceStatus $status
 * @property ?string $requested_by
 * @property Carbon $requested_at
 * @property ?string $decided_by
 * @property ?Carbon $decided_at
 * @property ?string $comment
 * @property ?CarbonImmutable $valid_until
 * @property-read ?User $requester
 * @property-read ?User $decider
 * @property-read Risk $risk
 */
#[Table('risk_acceptances')]
final class Acceptance extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    public $timestamps = false;

    public function score(): int
    {
        return $this->likelihood * $this->impact;
    }

    /**
     * @return BelongsTo<Risk, $this>
     */
    public function risk(): BelongsTo
    {
        return $this->belongsTo(Risk::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    protected function casts(): array
    {
        return [
            'status' => AcceptanceStatus::class,
            'likelihood' => 'integer',
            'impact' => 'integer',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'valid_until' => 'immutable_date',
        ];
    }
}
