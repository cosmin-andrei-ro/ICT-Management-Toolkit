<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Dcim\Tests\Support;

use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

final class DcimScenario
{
    public static function member(Organization $organization, DefaultRole $role): User
    {
        $factory = User::factory()->memberOf($organization);
        $user = ($role === DefaultRole::Requester ? $factory : $factory->withTwoFactor())->create();
        app(OrganizationContext::class)->runAs($organization, fn () => $user->assignRole($role->value));

        return User::query()->findOrFail($user->id);
    }
}
