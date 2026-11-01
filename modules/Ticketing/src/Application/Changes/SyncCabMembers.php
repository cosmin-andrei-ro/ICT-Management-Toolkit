<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Changes;

use InvalidArgumentException;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Models\CabBoard;

/**
 * Sets a board's members. Only members of the board's organization can sit on it:
 * a board decides who authorizes changes, so membership is audited.
 */
final readonly class SyncCabMembers
{
    public function __construct(private AuditTrail $trail) {}

    /**
     * @param  list<string>  $userIds
     */
    public function handle(CabBoard $board, array $userIds): void
    {
        $userIds = array_values(array_unique($userIds));

        $eligible = User::query()
            ->whereKey($userIds)
            ->whereHas('organizations', fn ($query) => $query->whereKey($board->organization_id))
            ->count();

        if ($eligible !== count($userIds)) {
            throw new InvalidArgumentException('Board members must belong to the board\'s organization.');
        }

        /** @var list<string> $previous */
        $previous = $board->members()->pluck('users.id')->sort()->values()->all();
        $board->members()->sync($userIds);
        sort($userIds);

        if ($previous !== $userIds) {
            // Pivot writes bypass the model observer.
            $this->trail->record(AuditEvent::Updated, $board, ['members' => $previous], ['members' => $userIds]);
        }
    }
}
