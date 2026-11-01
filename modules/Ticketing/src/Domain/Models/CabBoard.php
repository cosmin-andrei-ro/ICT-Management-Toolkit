<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Policies\TicketingSettingsPolicy;

/**
 * Change Advisory Board: every member except the change's requester must approve.
 * An emergency board (ECAB) authorizes emergency changes, a regular one the others.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property ?string $description
 * @property bool $is_emergency
 * @property bool $is_active
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 */
#[Table('ticketing_cab_boards')]
#[UsePolicy(TicketingSettingsPolicy::class)]
#[Fillable(['name', 'description', 'is_emergency', 'is_active'])]
final class CabBoard extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    /**
     * Mirror the column defaults, so new instances know them before saving.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_emergency' => false,
        'is_active' => true,
    ];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticketing_cab_members', 'board_id')->withTimestamps();
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    protected function casts(): array
    {
        return [
            'is_emergency' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
