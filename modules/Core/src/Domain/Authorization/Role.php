<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Authorization;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * The spatie base model is fully unguarded; roles must only be written through
 * Core actions, never filled from request data.
 *
 * @property string $id
 * @property ?string $organization_id
 * @property string $name
 * @property string $guard_name
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
final class Role extends SpatieRole
{
    use HasUuids;

    protected static function booted(): void
    {
        // Role::create() sets the team, but Role::query()->create() does not, and a
        // missing organization_id would silently produce a role shared by every
        // organization. A global role must be asked for with an explicit null.
        self::creating(function (self $role): void {
            if (! array_key_exists('organization_id', $role->getAttributes())) {
                $role->organization_id = app(OrganizationContext::class)->requireId();
            }
        });
    }

    public function isGlobal(): bool
    {
        return $this->organization_id === null;
    }
}
