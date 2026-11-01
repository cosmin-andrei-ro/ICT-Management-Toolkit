<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Directory\DirectoryGroupMapping;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * @extends Factory<DirectoryGroupMapping>
 */
final class DirectoryGroupMappingFactory extends Factory
{
    protected $model = DirectoryGroupMapping::class;

    public function definition(): array
    {
        return [
            'organization_id' => app(OrganizationContext::class)->id() ?? Organization::factory(),
            'group_dn' => 'cn='.fake()->unique()->word().',ou=groups,dc=example,dc=test',
            'role_id' => fn (): string => Role::query()->where('name', DefaultRole::Agent->value)->whereNull('organization_id')->firstOrFail()->id,
        ];
    }

    public function forRole(DefaultRole $role): static
    {
        return $this->state(fn (): array => [
            'role_id' => Role::query()->where('name', $role->value)->whereNull('organization_id')->firstOrFail()->id,
        ]);
    }
}
