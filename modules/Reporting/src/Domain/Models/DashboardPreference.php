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
use Modules\Core\Domain\Models\User;
use Modules\Reporting\Database\Factories\DashboardPreferenceFactory;

/**
 * How a person arranged their dashboard in the current organization.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $user_id
 * @property list<string> $hidden_metrics
 * @property list<string> $source_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Table('reporting_dashboard_preferences')]
#[UseFactory(DashboardPreferenceFactory::class)]
final class DashboardPreference extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<DashboardPreferenceFactory> */
    use HasFactory;

    use HasUuids;

    protected $attributes = [
        'hidden_metrics' => '[]',
        'source_order' => '[]',
    ];

    public static function of(User $user): self
    {
        return self::query()->where('user_id', $user->id)->first()
            ?? (new self)->forceFill(['user_id' => $user->id]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hidden_metrics' => 'array',
            'source_order' => 'array',
        ];
    }
}
