<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

use Modules\Core\Domain\Models\User;

/**
 * Used when the BIA module is disabled.
 */
final class NoBusinessProcesses implements BusinessProcesses
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function search(string $terms, int $limit = 10): array
    {
        return [];
    }

    public function find(array $ids): array
    {
        return [];
    }

    public function assessed(): array
    {
        return [];
    }

    public function newProcessUrl(string $name, User $viewer): ?string
    {
        return null;
    }
}
