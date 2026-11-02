<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

/**
 * Used when the Applications module is disabled.
 */
final class NoApplications implements Applications
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
}
