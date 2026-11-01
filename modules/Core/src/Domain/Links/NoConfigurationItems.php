<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Links;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Models\User;

/**
 * Used when the CMDB module is disabled.
 */
final class NoConfigurationItems implements ConfigurationItems
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function search(string $terms, int $limit = 10): array
    {
        return [];
    }

    public function linkedTo(Model $record): array
    {
        return [];
    }

    public function link(Model $record, string $itemId, User $actor): void {}

    public function unlink(Model $record, string $itemId, User $actor): void {}

    public function impactOf(Model $record): array
    {
        return [];
    }
}
