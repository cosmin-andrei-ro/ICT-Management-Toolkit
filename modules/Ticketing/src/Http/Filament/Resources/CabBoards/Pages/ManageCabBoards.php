<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Filament\Resources\CabBoards\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Ticketing\Application\Changes\SyncCabMembers;
use Modules\Ticketing\Domain\Models\CabBoard;
use Modules\Ticketing\Http\Filament\Resources\CabBoards\CabBoardResource;

final class ManageCabBoards extends ManageRecords
{
    protected static string $resource = CabBoardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(function (array $data): CabBoard {
                $board = CabBoard::query()->create(CabBoardResource::boardAttributes($data));
                app(SyncCabMembers::class)->handle($board, CabBoardResource::memberIds($data));

                return $board;
            }),
        ];
    }
}
