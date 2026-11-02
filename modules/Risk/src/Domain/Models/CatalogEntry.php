<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Risk\Application\Policies\RiskSettingsPolicy;
use Modules\Risk\Domain\Enums\CatalogKind;

/**
 * A threat or a vulnerability of the organization's catalog. Entries in use
 * are deactivated rather than deleted, so risks keep them.
 *
 * @property string $id
 * @property string $organization_id
 * @property CatalogKind $kind
 * @property string $name
 * @property ?string $description
 * @property bool $is_active
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
#[Table('risk_catalog_entries')]
#[UsePolicy(RiskSettingsPolicy::class)]
#[Fillable(['kind', 'name', 'description', 'is_active'])]
final class CatalogEntry extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
    ];

    public function isUsed(): bool
    {
        return $this->getConnection()->table('risk_risk_catalog')->where('entry_id', $this->id)->exists();
    }

    protected function casts(): array
    {
        return [
            'kind' => CatalogKind::class,
            'is_active' => 'boolean',
        ];
    }
}
