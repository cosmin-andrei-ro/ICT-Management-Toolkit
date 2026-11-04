<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Tests\Support;

use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Application\Teams\SyncTeamMembers;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Application\Actions\CreateTicket;
use Modules\Ticketing\Application\Actions\NewTicketData;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Models\Queue;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * An IT support setup ready to use: an organization with a team and default queue,
 * an agent in that team, a team lead and a requester. The context is set.
 */
final class TicketingScenario
{
    public Organization $organization;

    public Team $team;

    public Queue $queue;

    public User $agent;

    public User $lead;

    public User $requester;

    public static function create(): self
    {
        app(SyncPermissions::class)->handle();

        $scenario = new self;
        $scenario->organization = Organization::factory()->create();
        app(OrganizationContext::class)->set($scenario->organization);

        $scenario->agent = $scenario->member(DefaultRole::Agent);
        $scenario->lead = $scenario->member(DefaultRole::TeamLead);
        $scenario->requester = $scenario->member(DefaultRole::Requester);

        $scenario->team = Team::factory()->create(['name' => 'IT Support']);
        app(SyncTeamMembers::class)->handle($scenario->team, [$scenario->agent->id, $scenario->lead->id]);
        $scenario->queue = Queue::factory()->default()->create(['team_id' => $scenario->team->id, 'name' => 'First line']);

        return $scenario;
    }

    public function member(DefaultRole $role, ?Organization $organization = null): User
    {
        $organization ??= $this->organization;
        // Staff roles must use two-factor authentication.
        $factory = User::factory()->memberOf($organization);
        $user = ($role === DefaultRole::Requester ? $factory : $factory->withTwoFactor())->create();
        app(OrganizationContext::class)->runAs($organization, fn () => $user->assignRole($role->value));

        return User::query()->findOrFail($user->id);
    }

    public function ticket(?User $requester = null, string $title = 'Printer on floor 2 is offline', TicketType $type = TicketType::Incident, Impact $impact = Impact::Low, Urgency $urgency = Urgency::Low): Ticket
    {
        $requester ??= $this->requester;

        return app(CreateTicket::class)->handle(
            new NewTicketData($type, $title, 'It shows "offline" since this morning.', $impact, $urgency),
            $requester,
            $requester,
        );
    }
}
