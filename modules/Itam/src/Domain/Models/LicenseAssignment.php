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
 * One seat of a licence, used by a person or a device. Written by
 * ManageLicenseSeats only, which audits the change on the licence.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $license_id
 * @property ?string $user_id
 * @property ?string $asset_id
 * @property Carbon $assigned_at
 * @property ?string $assigned_by
 * @property-read License $license
 * @property-read ?User $user
 * @property-read ?Asset $asset
 */
#[Table('itam_license_assignments')]
final class LicenseAssignment extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    public $timestamps = false;

    /**
     * @return BelongsTo<License, $this>
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }
}
