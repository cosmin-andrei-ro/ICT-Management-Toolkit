<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

use Modules\Core\Domain\Models\User;

/**
 * Used when the Risk module is disabled.
 */
final class NoRisks implements Risks
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function concerning(string $assetType, string $assetId, User $viewer): array
    {
        return [];
    }

    public function assessedCount(string $assetType, string $assetId): int
    {
        return 0;
    }
}
