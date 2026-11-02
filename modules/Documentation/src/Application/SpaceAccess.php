<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Application;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Domain\Models\User;
use Modules\Documentation\Domain\Enums\SpaceVisibility;
use Modules\Documentation\Domain\Models\Space;

/**
 * Who reads and who edits a space (owner's decisions): documentation
 * managers everything; owners and members of editing teams edit; readers
 * follow the space's visibility - IT staff, everyone in the organization, or
 * the chosen teams.
 */
final readonly class SpaceAccess
{
    public function canRead(User $user, Space $space): bool
    {
        return Space::query()->whereKey($space->id)->tap(fn (Builder $query) => $this->readable($query, $user))->exists();
    }

    public function canEdit(User $user, Space $space): bool
    {
        return Space::query()->whereKey($space->id)->tap(fn (Builder $query) => $this->editable($query, $user))->exists();
    }

    /**
     * Narrows a query of spaces (of the current organization) to those the person reads.
     *
     * @param  Builder<Space>  $query
     */
    public function readable(Builder $query, User $user): void
    {
        if ($user->can('documentation.space.manage')) {
            return;
        }

        $staff = $user->can('documentation.staff.read');

        $query->where(function (Builder $access) use ($user, $staff): void {
            $access->where('visibility', SpaceVisibility::Everyone->value)
                ->when($staff, fn (Builder $inner) => $inner->orWhere('visibility', SpaceVisibility::Staff->value))
                ->orWhereHas('owners', fn (Builder $owners) => $owners->whereKey($user->id))
                ->orWhereHas('teams', fn (Builder $teams) => $teams->whereHas('members', fn (Builder $members) => $members->whereKey($user->id)));
        });
    }

    /**
     * @param  Builder<Space>  $query
     */
    public function editable(Builder $query, User $user): void
    {
        if ($user->can('documentation.space.manage')) {
            return;
        }

        $query->where(function (Builder $access) use ($user): void {
            $access->whereHas('owners', fn (Builder $owners) => $owners->whereKey($user->id))
                ->orWhereHas('teams', fn (Builder $teams) => $teams->where('documentation_space_teams.access', 'edit')->whereHas('members', fn (Builder $members) => $members->whereKey($user->id)));
        });
    }
}
