<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use InvalidArgumentException;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Department;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Models\User;

/**
 * The demo company every module's demo seeder builds on: its people (one
 * account per role), departments, teams, places, suppliers and contracts.
 */
final class DemoCompany
{
    /** @var array<string, User> */
    public array $users = [];

    /** @var array<string, Department> */
    public array $departments = [];

    /** @var array<string, Team> */
    public array $teams = [];

    /** @var array<string, Location> */
    public array $locations = [];

    /** @var array<string, Supplier> */
    public array $suppliers = [];

    /** @var array<string, Contract> */
    public array $contracts = [];

    /**
     * Ids of records other modules' demo seeders created, for the next ones
     * to link to: "cmdb.erp" => CMDB item id, "itam.core_switch" => asset id.
     *
     * @var array<string, string>
     */
    public array $refs = [];

    public function __construct(public readonly Organization $organization) {}

    public function ref(string $key): string
    {
        return $this->refs[$key] ?? throw new InvalidArgumentException("No demo record [{$key}].");
    }

    public function user(string $key): User
    {
        return $this->users[$key] ?? throw new InvalidArgumentException("No demo user [{$key}].");
    }

    /**
     * Employees without an IT role, who raise tickets and own business processes.
     *
     * @return list<User>
     */
    public function employees(): array
    {
        return array_values(array_filter($this->users, fn (User $user, string $key): bool => str_starts_with($key, 'employee'), ARRAY_FILTER_USE_BOTH));
    }
}
