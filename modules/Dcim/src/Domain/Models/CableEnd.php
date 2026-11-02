<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToOrganization;

/**
 * One end ("a" or "b") of a cable, on a port; a port has at most one.
 *
 * @property string $cable_id
 * @property string $organization_id
 * @property string $end
 * @property string $port_id
 * @property-read Cable $cable
 * @property-read Port $port
 */
#[Table('dcim_cable_ends')]
final class CableEnd extends Model
{
    use BelongsToOrganization;

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'port_id';

    protected $keyType = 'string';

    /**
     * @return BelongsTo<Cable, $this>
     */
    public function cable(): BelongsTo
    {
        return $this->belongsTo(Cable::class);
    }

    /**
     * @return BelongsTo<Port, $this>
     */
    public function port(): BelongsTo
    {
        return $this->belongsTo(Port::class);
    }
}
