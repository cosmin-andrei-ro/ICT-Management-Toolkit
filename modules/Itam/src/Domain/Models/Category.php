<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Forms\FormSchema;
use Modules\Itam\Application\Policies\CategoryPolicy;
use Modules\Itam\Database\Factories\CategoryFactory;

/**
 * A kind of asset (laptop, monitor, server…) and the attributes its assets carry.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $key
 * @property string $name
 * @property ?string $description
 * @property list<array<string, mixed>> $attribute_schema
 * @property bool $is_active
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 */
#[Table('itam_categories')]
#[UseFactory(CategoryFactory::class)]
#[UsePolicy(CategoryPolicy::class)]
#[Fillable(['key', 'name', 'description', 'attribute_schema', 'is_active'])]
final class Category extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'attribute_schema' => '[]',
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        // An invalid definition is never stored (ADR 0011).
        self::saving(function (self $category): void {
            $category->attribute_schema = FormSchema::fromArray($category->attribute_schema)->toArray();
        });
    }

    public function schema(): FormSchema
    {
        return FormSchema::fromArray($this->attribute_schema);
    }

    /**
     * @return HasMany<Asset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    protected function casts(): array
    {
        return [
            'attribute_schema' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
