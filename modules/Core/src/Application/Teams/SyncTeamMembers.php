<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Teams;

use InvalidArgumentException;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Models\User;

/**
 * Sets a team's members. Only members of the team's organization can join, so a
 * team can never route work to someone outside it.
 */
final readonly class SyncTeamMembers
{
    public function __construct(private AuditTrail $trail) {}

    /**
     * @param  list<string>  $userIds
     */
    public function handle(Team $team, array $userIds): void
    {
        $userIds = array_values(array_unique($userIds));

        $eligible = User::query()
            ->whereKey($userIds)
            ->whereHas('organizations', fn ($query) => $query->whereKey($team->organization_id))
            ->count();

        if ($eligible !== count($userIds)) {
            throw new InvalidArgumentException('Team members must belong to the team\'s organization.');
        }

        /** @var list<string> $previous */
        $previous = $team->members()->pluck('users.id')->sort()->values()->all();
        $team->members()->sync($userIds);
        sort($userIds);

        if ($previous !== $userIds) {
            // Pivot writes bypass the model observer.
            $this->trail->record(AuditEvent::Updated, $team, ['members' => $previous], ['members' => $userIds]);
        }
    }
}
