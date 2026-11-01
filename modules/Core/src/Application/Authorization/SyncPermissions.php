<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Authorization;

use Illuminate\Database\ConnectionInterface;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\Permission;
use Modules\Core\Domain\Authorization\PermissionRegistry;
use Modules\Core\Domain\Authorization\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Writes the code-defined permissions and default roles to the database.
 * Idempotent; run on every deployment (php artisan core:permissions:sync).
 */
final readonly class SyncPermissions
{
    private const string GUARD = 'web';

    public function __construct(
        private PermissionRegistry $registry,
        private PermissionRegistrar $registrar,
        private ConnectionInterface $db,
    ) {}

    /**
     * @return list<string> Permissions present in the database but no longer defined in code.
     *                      They are reported, not deleted: removing one silently revokes access.
     */
    public function handle(): array
    {
        return $this->db->transaction(function (): array {
            $defined = $this->registry->permissions();

            foreach ($defined as $name) {
                Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);
            }

            foreach (DefaultRole::cases() as $defaultRole) {
                $role = Role::query()->firstOrCreate([
                    'organization_id' => null,
                    'name' => $defaultRole->value,
                    'guard_name' => self::GUARD,
                ]);

                $this->syncRolePermissions($role, $this->registry->permissionsFor($defaultRole));
            }

            $this->registrar->forgetCachedPermissions();

            /** @var list<string> $stale */
            $stale = Permission::query()
                ->where('guard_name', self::GUARD)
                ->whereNotIn('name', $defined)
                ->orderBy('name')
                ->pluck('name')
                ->all();

            return $stale;
        });
    }

    /**
     * Applies only the difference, unlike Role::syncPermissions(), which detaches and
     * re-attaches everything and would flood the audit log on every deployment.
     *
     * @param  list<string>  $desired
     */
    private function syncRolePermissions(Role $role, array $desired): void
    {
        /** @var list<string> $current */
        $current = $role->permissions()->pluck('name')->all();

        $missing = array_values(array_diff($desired, $current));
        $extra = array_values(array_diff($current, $desired));

        if ($missing !== []) {
            $role->givePermissionTo($missing);
        }

        if ($extra !== []) {
            $role->revokePermissionTo($extra);
        }
    }
}
