<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Tests\Support;

use Modules\Bia\Application\Actions\ProvisionDefaultMatrix;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Bia\Domain\Models\ImpactCategory;
use Modules\Bia\Domain\Models\TimeHorizon;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Department;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * An organization in context with a continuity coordinator, a process owner and deputy
 * (plain employees), a team lead, an agent, another employee, the default
 * matrix and one process.
 */
final class BiaScenario
{
    public Organization $organization;

    public User $coordinator;

    public User $owner;

    public User $deputy;

    public User $lead;

    public User $agent;

    public User $employee;

    public BusinessProcess $payroll;

    public static function create(): self
    {
        app(SyncPermissions::class)->handle();

        $scenario = new self;
        $scenario->organization = Organization::factory()->create();
        app(OrganizationContext::class)->set($scenario->organization);
        $scenario->coordinator = $scenario->member(DefaultRole::ContinuityCoordinator);
        $scenario->owner = $scenario->member(DefaultRole::Requester);
        $scenario->deputy = $scenario->member(DefaultRole::Requester);
        $scenario->lead = $scenario->member(DefaultRole::TeamLead);
        $scenario->agent = $scenario->member(DefaultRole::Agent);
        $scenario->employee = $scenario->member(DefaultRole::Requester);
        app(ProvisionDefaultMatrix::class)->handle();
        $scenario->payroll = BusinessProcess::factory()->create([
            'name' => 'Payroll',
            'department_id' => Department::factory()->create(['name' => 'Finance'])->id,
            'owner_id' => $scenario->owner->id,
            'deputy_id' => $scenario->deputy->id,
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
     * Every cell of the default matrix, scored: low at first, unacceptable (4) from the given horizon on.
     *
     * @return array<string, string>
     */
    public function scores(int $unacceptableFromMinutes): array
    {
        $scores = [];

        foreach (ImpactCategory::query()->get() as $category) {
            foreach (TimeHorizon::query()->get() as $horizon) {
                $scores[$category->id.'|'.$horizon->id] = $horizon->minutes >= $unacceptableFromMinutes ? '4' : '2';
            }
        }

        return $scores;
    }
}
