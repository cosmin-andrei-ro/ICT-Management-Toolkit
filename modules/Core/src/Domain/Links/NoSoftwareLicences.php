<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

use Modules\Core\Domain\Models\User;

/**
 * Used when the asset module is disabled.
 */
final class NoSoftwareLicences implements SoftwareLicences
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function isRecorded(string $productName): bool
    {
        return false;
    }

    public function newLicenceUrl(string $productName, User $viewer): ?string
    {
        return null;
    }
}
