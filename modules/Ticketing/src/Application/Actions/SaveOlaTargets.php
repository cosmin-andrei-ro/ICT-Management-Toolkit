<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Ticketing\Domain\Models\TeamOla;

/**
 * One target per team; no value means no target. New targets apply to tickets
 * reaching the team afterwards: running clocks are not rewritten, as for SLAs.
 */
final readonly class SaveOlaTargets
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  array<string, ?int>  $targets  Team id => working minutes, for the organization's teams.
     */
    public function handle(array $targets): void
    {
        foreach ($targets as $minutes) {
            if ($minutes !== null && ($minutes < 1 || $minutes > 525600)) {
                throw new InvalidArgumentException('A target must be between one minute and a year.');
            }
        }

        $this->db->transaction(function () use ($targets): void {
            foreach ($targets as $teamId => $minutes) {
                $ola = TeamOla::query()->where('team_id', $teamId)->first();

                if ($minutes === null) {
                    $ola?->delete();
                } elseif ($ola === null) {
                    TeamOla::query()->create(['team_id' => $teamId, 'minutes' => $minutes]);
                } else {
                    $ola->update(['minutes' => $minutes]);
                }
            }
        });
    }
}
