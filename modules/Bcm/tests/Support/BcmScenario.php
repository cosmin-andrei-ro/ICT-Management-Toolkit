<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Tests\Support;

use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * An organization in context with a continuity coordinator, a team lead, an
 * agent, a plan owner (plain employee), another employee, a payroll process
 * with an RTO of 4 hours, and its application in the CMDB.
 */
final class BcmScenario
{
    public Organization $organization;

    public User $coordinator;

    public User $lead;

    public User $agent;

    public User $owner;

    public User $employee;

    public BusinessProcess $payroll;

    public ConfigurationItem $payrollApp;

    public static function create(): self
    {
        app(SyncPermissions::class)->handle();

        $scenario = new self;
        $scenario->organization = Organization::factory()->create();
        app(OrganizationContext::class)->set($scenario->organization);
        $scenario->coordinator = $scenario->member(DefaultRole::ContinuityCoordinator);
        $scenario->lead = $scenario->member(DefaultRole::TeamLead);
        $scenario->agent = $scenario->member(DefaultRole::Agent);
        $scenario->owner = $scenario->member(DefaultRole::Requester);
        $scenario->employee = $scenario->member(DefaultRole::Requester);
        $scenario->payroll = BusinessProcess::factory()->create(['name' => 'Payroll', 'owner_id' => $scenario->owner->id, 'rto_minutes' => 240, 'rpo_minutes' => 60, 'tier' => 1, 'approved_at' => now()]);
        $scenario->payrollApp = ConfigurationItem::factory()->create(['type_id' => CiType::factory()->create(['key' => 'application'])->id, 'name' => 'payroll-app']);

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
