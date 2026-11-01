<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Cmdb\Application\Policies\CmdbPolicy;
use Modules\Cmdb\Database\Factories\ConfigurationItemFactory;
use Modules\Cmdb\Domain\Enums\CiStatus;
use Modules\Cmdb\Domain\Enums\Criticality;
use Modules\Cmdb\Domain\Enums\Environment;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Models\User;

/**
 * Anything IT runs and must keep track of: a service, an application, a server,
 * a database… Written through SaveConfigurationItem, which validates the
 * attributes against the type.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $type_id
 * @property string $name
 * @property ?string $identifier
 * @property CiStatus $status
 * @property ?Environment $environment
 * @property Criticality $criticality
 * @property ?string $owner_id
 * @property ?string $team_id
 * @property ?string $description
 * @property array<string, string|int|float|bool|null> $properties Values of the type's attributes
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read CiType $type
 * @property-read ?User $owner
 * @property-read ?Team $team
 */
#[Table('cmdb_configuration_items')]
#[UseFactory(ConfigurationItemFactory::class)]
#[UsePolicy(CmdbPolicy::class)]
#[Fillable(['type_id', 'name', 'identifier', 'status', 'environment', 'criticality', 'owner_id', 'team_id', 'description'])]
final class ConfigurationItem extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<ConfigurationItemFactory> */
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
        'status' => 'in_use',
        'criticality' => 'medium',
        'properties' => '{}',
    ];

    /**
     * @return BelongsTo<CiType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(CiType::class, 'type_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Relationships where this item is the source ("runs on" …).
     *
     * @return HasMany<Relationship, $this>
     */
    public function outgoing(): HasMany
    {
        return $this->hasMany(Relationship::class, 'source_id');
    }

    /**
     * Relationships where this item is the target ("hosts" …).
     *
     * @return HasMany<Relationship, $this>
     */
    public function incoming(): HasMany
    {
        return $this->hasMany(Relationship::class, 'target_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, string $terms): void
    {
        $terms = trim($terms);

        if ($terms === '') {
            return;
        }

        $query->where(fn (Builder $inner) => $inner
            ->whereRaw("search_vector @@ websearch_to_tsquery('simple', ?)", [$terms])
            ->orWhereRaw("search_vector @@ websearch_to_tsquery('english', ?)", [$terms])
            ->orWhereRaw('identifier ILIKE ?', [str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $terms).'%']));
    }

    protected function casts(): array
    {
        return [
            'status' => CiStatus::class,
            'environment' => Environment::class,
            'criticality' => Criticality::class,
            'properties' => 'array',
        ];
    }
}
