<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Tests\Support;

use Modules\Applications\Domain\Models\Application;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * An organization in context with an application manager, a security officer,
 * an agent, a business owner (plain employee), a technical owner (team lead),
 * another employee, and the Ticketing Platform application they own.
 */
final class ApplicationsScenario
{
    public Organization $organization;

    public User $manager;

    public User $security;

    public User $agent;

    public User $businessOwner;

    public User $technicalOwner;

    public User $employee;

    public Application $ticketing;

    public static function create(): self
    {
        app(SyncPermissions::class)->handle();

        $scenario = new self;
        $scenario->organization = Organization::factory()->create();
        app(OrganizationContext::class)->set($scenario->organization);
        $scenario->manager = $scenario->member(DefaultRole::ApplicationManager);
        $scenario->security = $scenario->member(DefaultRole::SecurityOfficer);
        $scenario->agent = $scenario->member(DefaultRole::Agent);
        $scenario->businessOwner = $scenario->member(DefaultRole::Requester);
        $scenario->technicalOwner = $scenario->member(DefaultRole::TeamLead);
        $scenario->employee = $scenario->member(DefaultRole::Requester);
        $scenario->ticketing = Application::factory()->create([
            'name' => 'Ticketing Platform',
            'code' => 'APP-TKT',
            'business_owner_id' => $scenario->businessOwner->id,
            'technical_owner_id' => $scenario->technicalOwner->id,
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
}
