<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Bia\Application\Policies\BiaSettingsPolicy;
use Modules\Bia\Database\Factories\TimeHorizonFactory;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;

/**
 * A point in time after the start of an interruption at which impact is scored.
 * Its duration is fixed once scores use it.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $label
 * @property int $minutes
 * @property bool $is_active
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 */
#[Table('bia_time_horizons')]
#[UseFactory(TimeHorizonFactory::class)]
#[UsePolicy(BiaSettingsPolicy::class)]
#[Fillable(['label', 'minutes', 'is_active'])]
final class TimeHorizon extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<TimeHorizonFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
    ];

    public function isInUse(): bool
    {
        return ImpactScore::query()->where('horizon_id', $this->id)->exists();
    }

    protected function casts(): array
    {
        return [
            'minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
