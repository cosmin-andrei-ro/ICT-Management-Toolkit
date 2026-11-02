<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Tests\Support;

use Modules\Applications\Domain\Models\Application;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * An organization in context with a risk manager, a team lead (reads the
 * register), an agent, a risk owner (plain employee), another employee, and
 * something of each kind a risk can concern.
 */
final class RiskScenario
{
    public Organization $organization;

    public User $manager;

    public User $lead;

    public User $agent;

    public User $owner;

    public User $employee;

    public Application $payrollApp;

    public ConfigurationItem $payrollDb;

    public BusinessProcess $payroll;

    public Supplier $vendor;

    public static function create(): self
    {
        app(SyncPermissions::class)->handle();

        $scenario = new self;
        $scenario->organization = Organization::factory()->create();
        app(OrganizationContext::class)->set($scenario->organization);
        $scenario->manager = $scenario->member(DefaultRole::RiskManager);
        $scenario->lead = $scenario->member(DefaultRole::TeamLead);
        $scenario->agent = $scenario->member(DefaultRole::Agent);
        $scenario->owner = $scenario->member(DefaultRole::Requester);
        $scenario->employee = $scenario->member(DefaultRole::Requester);
        $scenario->payrollApp = Application::factory()->create(['name' => 'Payroll', 'code' => 'APP-PAY']);
        $scenario->payrollDb = ConfigurationItem::factory()->create(['type_id' => CiType::factory()->create(['key' => 'database'])->id, 'name' => 'PAYDB01']);
        $scenario->payroll = BusinessProcess::factory()->create(['name' => 'Monthly payroll', 'rto_minutes' => 240, 'approved_at' => now()]);
        $scenario->vendor = Supplier::factory()->create(['name' => 'Payroll Outsourcing SRL']);

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
