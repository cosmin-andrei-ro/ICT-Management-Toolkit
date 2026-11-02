<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Concerns\BelongsToOrganization;

/**
 * A configuration item the exercise was to bring back, and whether it did.
 * Recovered items are confirmed in the CMDB when the exercise is completed.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $exercise_id
 * @property string $configuration_item_id
 * @property ?bool $recovered
 * @property ?int $measured_minutes
 * @property ?string $notes
 */
#[Table('bcm_exercise_items')]
final class ExerciseItem extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'recovered' => 'boolean',
            'measured_minutes' => 'integer',
        ];
    }
}
