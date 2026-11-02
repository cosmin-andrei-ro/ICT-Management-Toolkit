<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Tests\Support;

use Livewire\Livewire;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bcm\Domain\Models\PlanVersion;
use Modules\Bcm\Http\Livewire\PlanVersionPage;
use Modules\Bcm\Http\Livewire\ShowPlan;
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

    /**
     * A payroll DRP in force, owned by the plan owner with the coordinator in its
     * crisis team: 1 hour to activate, 2 to restore the payroll application.
     */
    public function approvedPayrollDrp(): Plan
    {
        $plan = Plan::factory()->create(['name' => 'Payroll DRP', 'owner_id' => $this->owner->id]);
        test()->actingAs($this->owner);
        Livewire::test(ShowPlan::class, ['plan' => $plan])->call('startVersion');
        $version = PlanVersion::query()->where('plan_id', $plan->id)->sole();

        Livewire::test(PlanVersionPage::class, ['version' => $version])
            ->call('addProcess', $this->payroll->id)
            ->call('linkItem', $this->payrollApp->id)
            ->set('scenarios', ['it_outage'])
            ->set('activationCriteria', 'Payroll application down for more than 1 hour')
            ->set('memberRole', 'Incident manager')->set('memberUserId', $this->coordinator->id)->call('addMember')
            ->call('editStep')->set('stepPhase', 'activation')->set('stepAction', 'Declare the incident')->set('stepDurationValue', '1')->set('stepDurationUnit', 'hours')->call('saveStep')
            ->call('editStep')->set('stepPhase', 'recovery')->set('stepAction', 'Restore the database from backup')->set('stepDurationValue', '2')->set('stepDurationUnit', 'hours')->set('stepItemId', $this->payrollApp->id)->call('saveStep')
            ->call('submit')
            ->assertHasNoErrors();

        test()->actingAs($this->coordinator);
        Livewire::test(PlanVersionPage::class, ['version' => $version])->call('approve')->assertHasNoErrors();

        return $plan->fresh() ?? $plan;
    }

    public function member(DefaultRole $role): User
    {
        $factory = User::factory()->memberOf($this->organization);
        $user = ($role === DefaultRole::Requester ? $factory : $factory->withTwoFactor())->create();
        $user->assignRole($role->value);

        return User::query()->findOrFail($user->id);
    }
}
