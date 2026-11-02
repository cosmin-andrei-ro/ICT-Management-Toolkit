<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Models\User;

/**
 * Who had an asset, from when until when. Written by AssignAsset and
 * ReturnAsset only, which also audit the change on the asset.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $asset_id
 * @property string $user_id
 * @property Carbon $assigned_at
 * @property ?string $assigned_by
 * @property ?string $notes
 * @property ?Carbon $returned_at
 * @property ?string $returned_by
 * @property ?string $return_notes
 * @property ?Carbon $acknowledged_at When the holder confirmed in the portal that they received it.
 * @property ?string $acknowledged_ip
 * @property-read Asset $asset
 * @property-read User $user
 * @property-read ?User $assigner
 * @property-read ?User $receiver
 */
#[Table('itam_asset_assignments')]
final class AssetAssignment extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    public $timestamps = false;

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'returned_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }
}
