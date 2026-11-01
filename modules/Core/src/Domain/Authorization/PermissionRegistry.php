<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Authorization;

use InvalidArgumentException;

/**
 * Code-defined permissions and the default (global) roles that receive them.
 * Modules register here from their service provider; SyncPermissions writes the
 * result to the database.
 */
final class PermissionRegistry
{
    /** Permission names follow <module>.<entity>.<action> (ADR 0004). */
    private const string NAME_PATTERN = '/\A[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*\z/';

    /** @var array<string, true> */
    private array $permissions = [];

    /** @var array<string, array<string, true>> */
    private array $defaultRoles = [];

    /**
     * @param  list<string>  $names
     */
    public function register(array $names): void
    {
        foreach ($names as $name) {
            if (preg_match(self::NAME_PATTERN, $name) !== 1) {
                throw new InvalidArgumentException("Permission [{$name}] must follow <module>.<entity>.<action>.");
            }

            $this->permissions[$name] = true;
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    public function grantToDefaultRole(DefaultRole $role, array $permissions): void
    {
        $this->defaultRoles[$role->value] ??= [];

        foreach ($permissions as $permission) {
            $this->defaultRoles[$role->value][$permission] = true;
        }
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        $names = array_keys($this->permissions);
        sort($names);

        return $names;
    }

    /**
     * @return list<string>
     */
    public function permissionsFor(DefaultRole $role): array
    {
        if ($role === DefaultRole::Administrator) {
            return $this->permissions();
        }

        $names = array_keys($this->defaultRoles[$role->value] ?? []);
        $unknown = array_diff($names, $this->permissions());

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unregistered permissions granted to role ['.$role->value.']: '.implode(', ', $unknown));
        }

        sort($names);

        return $names;
    }
}
