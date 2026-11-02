<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\User;

/**
 * Something a phase of a project produces, possibly a record of another
 * module (the application, a CMDB item, a change, a plan…).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $project_id
 * @property string $phase_id
 * @property string $title
 * @property bool $required
 * @property ?string $linked_type
 * @property ?string $linked_id
 * @property ?string $linked_title
 * @property ?Carbon $delivered_at
 * @property ?string $delivered_by
 * @property Carbon $created_at
 * @property-read Phase $phase
 * @property-read ?User $deliverer
 */
#[Table('projects_deliverables')]
final class Deliverable extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;
    use TracksAuthors;

    protected $attributes = [
        'required' => true,
    ];

    /**
     * @return BelongsTo<Phase, $this>
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deliverer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function isDelivered(): bool
    {
        return $this->delivered_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'delivered_at' => 'datetime',
        ];
    }
}
