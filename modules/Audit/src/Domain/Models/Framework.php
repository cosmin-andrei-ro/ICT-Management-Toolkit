<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Audit\Application\Policies\FrameworkPolicy;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;

/**
 * A regulation, standard or internal policy the organization complies with.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $key
 * @property string $name
 * @property ?string $version
 * @property ?string $description
 * @property bool $has_soa
 * @property bool $is_active
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
#[Table('audit_frameworks')]
#[UsePolicy(FrameworkPolicy::class)]
final class Framework extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'has_soa' => false,
        'is_active' => true,
    ];

    /**
     * @return HasMany<Requirement, $this>
     */
    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class)->orderBy('position');
    }

    protected function casts(): array
    {
        return [
            'has_soa' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
