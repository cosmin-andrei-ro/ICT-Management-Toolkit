<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Cmdb\Application\Policies\CmdbPolicy;
use Modules\Cmdb\Domain\Enums\RelationshipType;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;

/**
 * "source <type> target". Written through ManageRelationships only.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $source_id
 * @property string $target_id
 * @property RelationshipType $type
 * @property ?string $description
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read ConfigurationItem $source
 * @property-read ConfigurationItem $target
 */
#[Table('cmdb_relationships')]
#[UsePolicy(CmdbPolicy::class)]
final class Relationship extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;
    use TracksAuthors;

    /**
     * @return BelongsTo<ConfigurationItem, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(ConfigurationItem::class, 'source_id');
    }

    /**
     * @return BelongsTo<ConfigurationItem, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(ConfigurationItem::class, 'target_id');
    }

    protected function casts(): array
    {
        return [
            'type' => RelationshipType::class,
        ];
    }
}
