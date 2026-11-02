<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Reporting\Database\Factories\MetricSnapshotFactory;

/**
 * The value of an indicator on a day.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $metric_key
 * @property Carbon $taken_on
 * @property ?string $value
 * @property Carbon $created_at
 */
#[Table('reporting_metric_snapshots')]
#[UseFactory(MetricSnapshotFactory::class)]
final class MetricSnapshot extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<MetricSnapshotFactory> */
    use HasFactory;

    use HasUuids;

    public const ?string UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taken_on' => 'date',
        ];
    }
}
