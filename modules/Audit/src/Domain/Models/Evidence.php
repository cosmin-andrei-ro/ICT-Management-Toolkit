<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Audit\Application\Policies\EvidencePolicy;
use Modules\Audit\Domain\Enums\EvidenceKind;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\HasAttachments;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\User;

/**
 * What shows a control works: a record of the toolkit (an approved change, an
 * exercise, a risk…) or a document (a report, a policy, a screenshot), with
 * how long it stays valid.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $control_id
 * @property ?string $test_id
 * @property EvidenceKind $kind
 * @property string $title
 * @property ?string $description
 * @property ?string $linked_type
 * @property ?string $linked_id
 * @property ?CarbonImmutable $valid_until
 * @property ?string $created_by
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read Control $control
 * @property-read ?User $creator
 */
#[Table('audit_evidence')]
#[UsePolicy(EvidencePolicy::class)]
final class Evidence extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    public function isExpired(CarbonImmutable $today): bool
    {
        return $this->valid_until !== null && $this->valid_until->lt($today);
    }

    /**
     * @return BelongsTo<Control, $this>
     */
    public function control(): BelongsTo
    {
        return $this->belongsTo(Control::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'kind' => EvidenceKind::class,
            'valid_until' => 'immutable_date',
        ];
    }
}
