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
use Modules\Bia\Database\Factories\ImpactCategoryFactory;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;

/**
 * A kind of impact (financial, legal…), scored 1–5 with a description per level.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property ?string $description
 * @property list<string> $levels Descriptions of levels 1 to 5.
 * @property int $sort_order
 * @property bool $is_active
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 */
#[Table('bia_impact_categories')]
#[UseFactory(ImpactCategoryFactory::class)]
#[UsePolicy(BiaSettingsPolicy::class)]
#[Fillable(['name', 'description', 'levels', 'sort_order', 'is_active'])]
final class ImpactCategory extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<ImpactCategoryFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'levels' => '[]',
        'sort_order' => 0,
        'is_active' => true,
    ];

    public function levelDescription(int $level): ?string
    {
        $description = $this->levels[$level - 1] ?? null;

        return is_string($description) && $description !== '' ? $description : null;
    }

    protected function casts(): array
    {
        return [
            'levels' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
