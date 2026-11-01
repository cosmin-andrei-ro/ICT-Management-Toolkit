<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToOrganization;

/**
 * A record of another module (a ticket…) linked to an item.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $configuration_item_id
 * @property string $linkable_type
 * @property string $linkable_id
 * @property ?string $created_by
 * @property Carbon $created_at
 * @property-read ConfigurationItem $configurationItem
 */
#[Table('cmdb_ci_links')]
final class CiLink extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<ConfigurationItem, $this>
     */
    public function configurationItem(): BelongsTo
    {
        return $this->belongsTo(ConfigurationItem::class);
    }
}
