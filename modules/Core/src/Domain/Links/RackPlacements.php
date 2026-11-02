<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

use Modules\Core\Domain\Models\User;

/**
 * Where an asset is mounted, for the asset's own page: implemented by DCIM;
 * when it is disabled, nowhere.
 */
interface RackPlacements
{
    /**
     * The rack and units the asset occupies, if the viewer may see racks.
     */
    public function of(string $assetId, User $viewer): ?LinkedRecord;
}
