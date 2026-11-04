<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

use Modules\Core\Domain\Models\User;

/**
 * Whether a piece of software is in the licence register, for modules that document
 * software. Implemented by the asset module; when it is disabled, nothing is known.
 */
interface SoftwareLicences
{
    public function isAvailable(): bool;

    public function isRecorded(string $productName): bool;

    /**
     * Where this person can record a licence for it; null when they may not.
     */
    public function newLicenceUrl(string $productName, User $viewer): ?string;
}
