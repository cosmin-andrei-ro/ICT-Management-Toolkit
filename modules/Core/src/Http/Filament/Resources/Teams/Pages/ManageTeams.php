<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Filament\Resources\Teams\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Core\Application\Teams\SyncTeamMembers;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Http\Filament\Resources\Teams\TeamResource;

final class ManageTeams extends ManageRecords
{
    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(function (array $data): Team {
                $team = Team::query()->create(TeamResource::teamAttributes($data));
                app(SyncTeamMembers::class)->handle($team, TeamResource::memberIds($data));

                return $team;
            }),
        ];
    }
}
