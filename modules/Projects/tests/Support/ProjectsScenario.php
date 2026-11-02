<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Tests\Support;

use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

final class ProjectsScenario
{
    public Organization $organization;

    public User $manager;

    public User $sponsor;

    public User $engineer;

    public User $outsider;

    public static function create(): self
    {
        app(SyncPermissions::class)->handle();

        $scenario = new self;
        $scenario->organization = Organization::factory()->create();
        app(OrganizationContext::class)->set($scenario->organization);
        $scenario->manager = $scenario->member(DefaultRole::ProjectManager);
        $scenario->sponsor = $scenario->member(DefaultRole::Requester);
        $scenario->engineer = $scenario->member(DefaultRole::Agent);
        $scenario->outsider = $scenario->member(DefaultRole::Agent);

        return $scenario;
    }

    public function member(DefaultRole $role, ?Organization $organization = null): User
    {
        $organization ??= $this->organization;
        $factory = User::factory()->memberOf($organization);
        $user = ($role === DefaultRole::Requester ? $factory : $factory->withTwoFactor())->create();
        app(OrganizationContext::class)->runAs($organization, fn () => $user->assignRole($role->value));

        return User::query()->findOrFail($user->id);
    }
}
