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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Dcim\Database\Factories\PortFactory;
use Modules\Dcim\Domain\Enums\MountFace;
use Modules\Dcim\Domain\Enums\PortKind;

/**
 * A port of mounted equipment. A patch panel's front port is wired to one
 * rear port, which a path follows through the panel.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $mount_id
 * @property string $name
 * @property PortKind $kind
 * @property MountFace $side
 * @property ?string $rear_port_id
 * @property int $position
 * @property ?string $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Mount $mount
 * @property-read ?Port $rearPort
 * @property-read ?Port $frontPort
 * @property-read ?CableEnd $cableEnd
 */
#[Table('dcim_ports')]
#[UseFactory(PortFactory::class)]
final class Port extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<PortFactory> */
    use HasFactory;

    use HasUuids;
    use TracksAuthors;

    protected $attributes = [
        'side' => 'front',
        'position' => 0,
    ];

    /**
     * @return BelongsTo<Mount, $this>
     */
    public function mount(): BelongsTo
    {
        return $this->belongsTo(Mount::class);
    }

    /**
     * @return BelongsTo<Port, $this>
     */
    public function rearPort(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rear_port_id');
    }

    /**
     * @return HasOne<Port, $this>
     */
    public function frontPort(): HasOne
    {
        return $this->hasOne(self::class, 'rear_port_id');
    }

    /**
     * @return HasOne<CableEnd, $this>
     */
    public function cableEnd(): HasOne
    {
        return $this->hasOne(CableEnd::class);
    }

    /**
     * The port on the other side of a patch panel, if any.
     */
    public function passesTo(): ?self
    {
        return $this->rearPort ?? $this->frontPort;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PortKind::class,
            'side' => MountFace::class,
            'position' => 'integer',
        ];
    }
}
