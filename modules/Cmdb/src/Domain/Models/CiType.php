<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Domain\Models;

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
use Modules\Cmdb\Application\Policies\CiTypePolicy;
use Modules\Cmdb\Database\Factories\CiTypeFactory;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Forms\FormSchema;

/**
 * A kind of configuration item and the attributes its items carry.
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
#[Table('cmdb_ci_types')]
#[UseFactory(CiTypeFactory::class)]
#[UsePolicy(CiTypePolicy::class)]
#[Fillable(['key', 'name', 'description', 'attribute_schema', 'is_active'])]
final class CiType extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<CiTypeFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    /**
     * Mirror the column defaults, so new instances know them before saving.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'attribute_schema' => '[]',
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        // An invalid definition is never stored (ADR 0011).
        self::saving(function (self $type): void {
            $type->attribute_schema = FormSchema::fromArray($type->attribute_schema)->toArray();
        });
    }

    public function schema(): FormSchema
    {
        return FormSchema::fromArray($this->attribute_schema);
    }

    /**
     * @return HasMany<ConfigurationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ConfigurationItem::class, 'type_id');
    }

    protected function casts(): array
    {
        return [
            'attribute_schema' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
