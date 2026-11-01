<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Ticketing\Application\Policies\TicketingSettingsPolicy;
use Modules\Ticketing\Database\Factories\CategoryFactory;

/**
 * @property string $id
 * @property string $organization_id
 * @property ?string $parent_id
 * @property string $name
 * @property ?string $description
 * @property bool $is_active
 * @property int $sort_order
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read ?Category $parent
 */
#[Table('ticketing_categories')]
#[UseFactory(CategoryFactory::class)]
#[UsePolicy(TicketingSettingsPolicy::class)]
#[Fillable(['parent_id', 'name', 'description', 'is_active', 'sort_order'])]
final class Category extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function fullName(): string
    {
        return $this->parent !== null ? $this->parent->name.' › '.$this->name : $this->name;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
