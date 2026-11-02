<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Tests\Support;

use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * An organization in context with a compliance manager, an auditor, a
 * security officer, a control owner (plain employee) and another employee.
 */
final class ComplianceScenario
{
    public Organization $organization;

    public User $manager;

    public User $auditor;

    public User $security;

    public User $owner;

    public User $employee;

    public static function create(): self
    {
        app(SyncPermissions::class)->handle();

        $scenario = new self;
        $scenario->organization = Organization::factory()->create();
        app(OrganizationContext::class)->set($scenario->organization);
        $scenario->manager = $scenario->member(DefaultRole::ComplianceManager);
        $scenario->auditor = $scenario->member(DefaultRole::Auditor);
        $scenario->security = $scenario->member(DefaultRole::SecurityOfficer);
        $scenario->owner = $scenario->member(DefaultRole::Requester);
        $scenario->employee = $scenario->member(DefaultRole::Requester);

        return $scenario;
    }

    public function member(DefaultRole $role): User
    {
        $factory = User::factory()->memberOf($this->organization);
        $user = ($role === DefaultRole::Requester ? $factory : $factory->withTwoFactor())->create();
        $user->assignRole($role->value);

        return User::query()->findOrFail($user->id);
    }
}
