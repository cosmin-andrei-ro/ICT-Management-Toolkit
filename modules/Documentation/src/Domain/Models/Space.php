<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Models\User;
use Modules\Documentation\Application\Policies\SpacePolicy;
use Modules\Documentation\Database\Factories\SpaceFactory;
use Modules\Documentation\Domain\Enums\SpaceVisibility;

/**
 * An area of the documentation (network, service desk procedures, guides for
 * employees…) with its own readers, editors and owners.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property ?string $description
 * @property SpaceVisibility $visibility
 * @property bool $requires_approval
 * @property int $review_months
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 */
#[Table('documentation_spaces')]
#[UseFactory(SpaceFactory::class)]
#[UsePolicy(SpacePolicy::class)]
final class Space extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<SpaceFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'visibility' => 'staff',
        'requires_approval' => false,
        'review_months' => 12,
    ];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'documentation_space_owners', 'space_id', 'user_id');
    }

    /**
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'documentation_space_teams', 'space_id', 'team_id')->withPivot('access');
    }

    /**
     * @return array<string, string> Team id => "read" or "edit".
     */
    public function teamAccess(): array
    {
        $access = [];

        foreach ($this->getConnection()->table('documentation_space_teams')->where('space_id', $this->id)->orderBy('team_id')->get(['team_id', 'access']) as $row) {
            if (is_string($row->team_id) && is_string($row->access)) {
                $access[$row->team_id] = $row->access;
            }
        }

        return $access;
    }

    /**
     * @return HasMany<Page, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    protected function casts(): array
    {
        return [
            'visibility' => SpaceVisibility::class,
            'requires_approval' => 'boolean',
            'review_months' => 'integer',
        ];
    }
}
