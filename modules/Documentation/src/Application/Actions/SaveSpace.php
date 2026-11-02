<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Documentation\Domain\Enums\SpaceVisibility;
use Modules\Documentation\Domain\Models\Space;

/**
 * Creates or changes a space: who reads it, who edits it, who owns it.
 * Membership changes are recorded on the space's audit trail.
 */
final readonly class SaveSpace
{
    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
        private AuditTrail $trail,
    ) {}

    /**
     * @param  array{name: string, description: ?string, visibility: SpaceVisibility, requires_approval: bool, review_months: int}  $data  Validated.
     * @param  list<string>  $ownerIds  Members of the organization.
     * @param  list<string>  $readTeamIds
     * @param  list<string>  $editTeamIds
     */
    public function handle(?Space $space, array $data, array $ownerIds, array $readTeamIds, array $editTeamIds, User $actor): Space
    {
        return $this->db->transaction(function () use ($space, $data, $ownerIds, $readTeamIds, $editTeamIds, $actor): Space {
            $space ??= new Space;
            $description = $data['description'] !== null ? trim($data['description']) : '';
            $space->forceFill([
                'name' => trim($data['name']),
                'description' => $description !== '' ? $description : null,
                'visibility' => $data['visibility'],
                'requires_approval' => $data['requires_approval'],
                'review_months' => $data['review_months'],
            ])->save();

            $before = $this->members($space);
            // Scoped lookups: people and teams of another organization are left out.
            $owners = User::query()->whereKey($ownerIds)->whereHas('organizations', fn ($query) => $query->whereKey($this->context->requireId()))->pluck('id')->all();
            $teams = array_filter(Team::query()->whereKey([...$readTeamIds, ...$editTeamIds])->pluck('id')->all(), is_string(...));
            $space->owners()->sync($owners);
            $access = [];

            foreach ($teams as $teamId) {
                // Editing includes reading.
                $access[$teamId] = ['access' => in_array($teamId, $editTeamIds, true) ? 'edit' : 'read'];
            }

            $space->teams()->sync($access);
            $after = $this->members($space);

            if ($before !== $after) {
                $this->trail->record(AuditEvent::Updated, $space, $before, $after, $actor->id);
            }

            return $space;
        });
    }

    /**
     * @return array{owners: list<string>, teams: array<string, string>}
     */
    private function members(Space $space): array
    {
        if (! $space->exists) {
            return ['owners' => [], 'teams' => []];
        }

        $owners = array_values(array_filter($space->owners()->pluck('users.id')->sort()->values()->all(), is_string(...)));
        $teams = $space->teamAccess();

        return ['owners' => $owners, 'teams' => $teams];
    }
}
