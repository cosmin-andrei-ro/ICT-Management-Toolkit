<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

final readonly class NoPhysicalAssets implements PhysicalAssets
{
    public function find(array $ids): array
    {
        return [];
    }

    public function search(string $term, int $limit = 20): array
    {
        return [];
    }
}
