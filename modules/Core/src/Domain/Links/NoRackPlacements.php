<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

use Modules\Core\Domain\Models\User;

final readonly class NoRackPlacements implements RackPlacements
{
    public function of(string $assetId, User $viewer): ?LinkedRecord
    {
        return null;
    }
}
