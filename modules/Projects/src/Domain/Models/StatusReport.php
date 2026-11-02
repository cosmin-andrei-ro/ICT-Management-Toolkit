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
use Modules\Core\Domain\Models\User;
use Modules\Projects\Domain\Enums\Rag;

/**
 * Where a project stands on a date, as its manager sees it.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $project_id
 * @property Carbon $reported_on
 * @property Rag $overall
 * @property Rag $schedule
 * @property Rag $budget
 * @property Rag $scope
 * @property string $achievements
 * @property string $next_steps
 * @property ?string $issues
 * @property ?string $author_id
 * @property Carbon $created_at
 * @property-read ?User $author
 */
#[Table('projects_status_reports')]
final class StatusReport extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reported_on' => 'date',
            'overall' => Rag::class,
            'schedule' => Rag::class,
            'budget' => Rag::class,
            'scope' => Rag::class,
        ];
    }
}
