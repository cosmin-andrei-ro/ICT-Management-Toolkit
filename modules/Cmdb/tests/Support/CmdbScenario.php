<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Tests\Support;

use Modules\Cmdb\Application\Actions\ManageRelationships;
use Modules\Cmdb\Domain\Enums\RelationshipType;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * An organization in context, with IT staff, a requester and a generic item type.
 */
final class CmdbScenario
{
    public Organization $organization;

    public User $agent;

    public User $lead;

    public User $requester;

    public CiType $type;

    public static function create(): self
    {
        app(SyncPermissions::class)->handle();

        $scenario = new self;
        $scenario->organization = Organization::factory()->create();
        app(OrganizationContext::class)->set($scenario->organization);
        $scenario->agent = $scenario->member(DefaultRole::Agent);
        $scenario->lead = $scenario->member(DefaultRole::TeamLead);
        $scenario->requester = $scenario->member(DefaultRole::Requester);
        $scenario->type = CiType::factory()->create(['key' => 'generic', 'name' => 'Generic']);

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
     * @param  array<string, mixed>  $attributes
     */
    public function item(string $name, array $attributes = []): ConfigurationItem
    {
        return ConfigurationItem::factory()->create(['type_id' => $this->type->id, 'name' => $name, ...$attributes]);
    }

    public function relate(ConfigurationItem $source, RelationshipType $type, ConfigurationItem $target): void
    {
        app(ManageRelationships::class)->add($source, $type, $target);
    }
}
