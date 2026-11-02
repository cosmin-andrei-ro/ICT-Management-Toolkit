<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Models\User;

/**
 * A role of the crisis team, filled by a person of the organization or an
 * outside contact (business details only). Written by ManagePlanContent, which
 * audits it on the version.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $version_id
 * @property int $position
 * @property string $role
 * @property ?string $user_id
 * @property ?string $contact_name
 * @property ?string $contact_phone
 * @property ?string $contact_email
 * @property bool $is_deputy
 * @property ?string $responsibilities
 * @property-read ?User $user
 */
#[Table('bcm_team_members')]
final class TeamMember extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    public $timestamps = false;

    public function displayName(): string
    {
        return $this->user->name ?? (string) $this->contact_name;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_deputy' => 'boolean',
        ];
    }
}
