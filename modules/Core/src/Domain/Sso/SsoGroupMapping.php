<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Sso;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;
use Modules\Core\Application\Policies\SsoPolicy;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;

/**
 * Members of a group of the identity provider get the role in the provider's
 * organization. For accounts created by single sign-on these mappings are the
 * only source of roles.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $provider_id
 * @property string $group
 * @property string $role_id
 * @property ?Carbon $created_at
 * @property-read Role $role
 * @property-read SsoProvider $provider
 */
#[Table('core_sso_group_mappings')]
#[UsePolicy(SsoPolicy::class)]
#[Fillable(['provider_id', 'group', 'role_id'])]
final class SsoGroupMapping extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;

    protected static function booted(): void
    {
        // A role of another organization would grant access defined by someone else.
        $ensureRoleBelongs = function (self $mapping): void {
            $role = Role::query()->find($mapping->role_id);

            if ($role === null || ($role->organization_id !== null && $role->organization_id !== $mapping->organization_id)) {
                throw new LogicException('A group mapping must use a global role or a role of its own organization.');
            }
        };

        self::creating($ensureRoleBelongs);
        self::updating($ensureRoleBelongs);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<SsoProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(SsoProvider::class);
    }
}
