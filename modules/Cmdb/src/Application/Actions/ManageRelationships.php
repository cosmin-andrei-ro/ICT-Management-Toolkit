<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Application\Actions;

use InvalidArgumentException;
use Modules\Cmdb\Domain\Enums\RelationshipType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Cmdb\Domain\Models\Relationship;

/**
 * Adds and removes "source <type> target" relationships between items of the
 * same organization (also enforced by composite foreign keys).
 */
final class ManageRelationships
{
    public function add(ConfigurationItem $source, RelationshipType $type, ConfigurationItem $target, ?string $description = null): Relationship
    {
        if ($source->is($target)) {
            throw new InvalidArgumentException('An item cannot relate to itself.');
        }

        if ($source->organization_id !== $target->organization_id) {
            throw new InvalidArgumentException('Both items must belong to the same organization.');
        }

        $existing = Relationship::query()->where('source_id', $source->id)->where('target_id', $target->id)->where('type', $type)->first();

        if ($existing !== null) {
            return $existing;
        }

        $relationship = new Relationship;
        $relationship->forceFill([
            'organization_id' => $source->organization_id,
            'source_id' => $source->id,
            'target_id' => $target->id,
            'type' => $type,
            'description' => $description !== null && trim($description) !== '' ? mb_substr(trim($description), 0, 255) : null,
        ])->save();

        return $relationship;
    }

    public function remove(Relationship $relationship): void
    {
        $relationship->delete();
    }
}
