<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\User;
use Modules\Projects\Database\Factories\TaskFactory;
use Modules\Projects\Domain\Enums\TaskStatus;

/**
 * A piece of work in a phase of a project.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $project_id
 * @property string $phase_id
 * @property string $title
 * @property ?string $description
 * @property ?string $assignee_id
 * @property ?Carbon $due_on
 * @property TaskStatus $status
 * @property int $position
 * @property ?Carbon $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Project $project
 * @property-read Phase $phase
 * @property-read ?User $assignee
 */
#[Table('projects_tasks')]
#[UseFactory(TaskFactory::class)]
final class Task extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    use HasUuids;
    use TracksAuthors;

    protected $attributes = [
        'status' => 'todo',
        'position' => 0,
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

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
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function isOverdue(): bool
    {
        return $this->status !== TaskStatus::Done && $this->due_on !== null && $this->due_on->isBefore(today());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'position' => 'integer',
            'due_on' => 'date',
            'completed_at' => 'datetime',
        ];
    }
}
