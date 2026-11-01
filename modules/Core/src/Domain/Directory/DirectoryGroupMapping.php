<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Directory;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;
use Modules\Core\Application\Policies\DirectoryGroupMappingPolicy;
use Modules\Core\Database\Factories\DirectoryGroupMappingFactory;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;

/**
 * Members of an AD group (directly or through nested groups) get the role in the
 * mapping's organization. For directory accounts these mappings are the only source
 * of organization membership and roles.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $group_dn
 * @property string $role_id
 * @property ?string $created_by
 * @property ?string $updated_by
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read Role $role
 */
#[Table('core_directory_group_mappings')]
#[UseFactory(DirectoryGroupMappingFactory::class)]
#[UsePolicy(DirectoryGroupMappingPolicy::class)]
#[Fillable(['group_dn', 'role_id'])]
final class DirectoryGroupMapping extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<DirectoryGroupMappingFactory> */
    use HasFactory;

    use HasUuids;
    use TracksAuthors;

    protected static function booted(): void
    {
        // A role of another organization would grant access defined by someone else.
        // On "creating", not "saving": organization_id is filled by BelongsToOrganization
        // on "creating", which runs first.
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
     * @return Attribute<string, string>
     */
    protected function groupDn(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => Str::lower(trim($value)));
    }
}
