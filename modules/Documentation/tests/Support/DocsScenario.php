<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Tests\Support;

use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * An organization in context with a team lead (manages documentation), an
 * agent (IT staff), a network engineer who is a plain employee but member of
 * the Network team, and another employee.
 */
final class DocsScenario
{
    public Organization $organization;

    public User $lead;

    public User $agent;

    public User $engineer;

    public User $employee;

    public Team $network;

    public static function create(): self
    {
        app(SyncPermissions::class)->handle();

        $scenario = new self;
        $scenario->organization = Organization::factory()->create();
        app(OrganizationContext::class)->set($scenario->organization);
        $scenario->lead = $scenario->member(DefaultRole::TeamLead);
        $scenario->agent = $scenario->member(DefaultRole::Agent);
        $scenario->engineer = $scenario->member(DefaultRole::Requester);
        $scenario->employee = $scenario->member(DefaultRole::Requester);
        $scenario->network = Team::factory()->create(['name' => 'Network']);
        $scenario->network->members()->attach($scenario->engineer->id);

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
