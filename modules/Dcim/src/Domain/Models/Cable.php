<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Dcim\Database\Factories\CableFactory;
use Modules\Dcim\Domain\Enums\CableStatus;
use Modules\Dcim\Domain\Enums\CableType;

/**
 * A cable between two ports.
 *
 * @property string $id
 * @property string $organization_id
 * @property ?string $label
 * @property CableType $type
 * @property ?string $colour
 * @property ?string $length_m
 * @property CableStatus $status
 * @property ?string $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CableEnd> $ends
 */
#[Table('dcim_cables')]
#[UseFactory(CableFactory::class)]
final class Cable extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<CableFactory> */
    use HasFactory;

    use HasUuids;
    use TracksAuthors;

    protected $attributes = [
        'status' => 'connected',
    ];

    /**
     * @return HasMany<CableEnd, $this>
     */
    public function ends(): HasMany
    {
        return $this->hasMany(CableEnd::class)->orderBy('end');
    }

    /**
     * The port at the other end from this one.
     */
    public function farEnd(Port $from): ?Port
    {
        return $this->ends->first(fn (CableEnd $end): bool => $end->port_id !== $from->id)?->port;
    }

    public function displayLabel(): string
    {
        return $this->label ?? __('dcim::cabling.unlabelled');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CableType::class,
            'status' => CableStatus::class,
        ];
    }
}
