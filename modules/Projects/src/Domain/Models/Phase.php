<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Projects\Domain\Enums\PhaseKey;
use Modules\Projects\Domain\Enums\PhaseStatus;

/**
 * One of the four phases of a project.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $project_id
 * @property PhaseKey $key
 * @property int $position
 * @property PhaseStatus $status
 * @property ?Carbon $planned_start
 * @property ?Carbon $planned_end
 * @property ?Carbon $started_at
 * @property ?Carbon $completed_at
 * @property-read Project $project
 */
#[Table('projects_phases')]
final class Phase extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;

    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => PhaseKey::class,
            'status' => PhaseStatus::class,
            'position' => 'integer',
            'planned_start' => 'date',
            'planned_end' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
