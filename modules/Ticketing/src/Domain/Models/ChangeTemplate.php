<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Ticketing\Application\Policies\TicketingSettingsPolicy;
use Modules\Ticketing\Domain\Changes\ChangeRisk;

/**
 * A pre-approved routine change (ITIL "standard change"). Creating a change from it
 * needs no CAB; its plans are copied and cannot be edited on the change.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string $description
 * @property ChangeRisk $risk
 * @property string $implementation_plan
 * @property string $rollback_plan
 * @property ?string $test_plan
 * @property bool $is_active
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 */
#[Table('ticketing_change_templates')]
#[UsePolicy(TicketingSettingsPolicy::class)]
#[Fillable(['name', 'description', 'risk', 'implementation_plan', 'rollback_plan', 'test_plan', 'is_active'])]
final class ChangeTemplate extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    /**
     * Mirror the column defaults, so new instances know them before saving.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'risk' => 'low',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'risk' => ChangeRisk::class,
            'is_active' => 'boolean',
        ];
    }
}
