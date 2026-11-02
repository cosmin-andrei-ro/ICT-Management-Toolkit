<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Tests\Support;

use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\Category;

/**
 * An organization in context with an asset manager, an agent, two employees and a laptop category.
 */
final class ItamScenario
{
    public Organization $organization;

    public User $manager;

    public User $agent;

    public User $employee;

    public User $colleague;

    public Category $laptops;

    public static function create(): self
    {
        app(SyncPermissions::class)->handle();

        $scenario = new self;
        $scenario->organization = Organization::factory()->create();
        app(OrganizationContext::class)->set($scenario->organization);
        $scenario->manager = $scenario->member(DefaultRole::AssetManager);
        $scenario->agent = $scenario->member(DefaultRole::Agent);
        $scenario->employee = $scenario->member(DefaultRole::Requester);
        $scenario->colleague = $scenario->member(DefaultRole::Requester);
        $scenario->laptops = Category::factory()->create([
            'key' => 'laptop',
            'name' => 'Laptop',
            'attribute_schema' => [['key' => 'memory_gb', 'label' => 'Memory (GB)', 'type' => 'number']],
        ]);

        return $scenario;
    }

    public function member(DefaultRole $role): User
    {
        $factory = User::factory()->memberOf($this->organization);
        $user = ($role === DefaultRole::Requester ? $factory : $factory->withTwoFactor())->create();
        $user->assignRole($role->value);

        return User::query()->findOrFail($user->id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function laptop(array $attributes = []): Asset
    {
        return Asset::factory()->create(['category_id' => $this->laptops->id, ...$attributes]);
    }
}
