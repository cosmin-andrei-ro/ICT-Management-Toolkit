<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Application\Teams\SyncTeamMembers;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Models\Category;
use Modules\Ticketing\Domain\Models\Queue;
use Modules\Ticketing\Domain\Models\Service;

/**
 * A working service desk for local demos: team, default queue, categories and two
 * catalog services. Called by DatabaseSeeder, which refuses to run outside local/testing.
 */
final class TicketingDemoSeeder extends Seeder
{
    public function run(Organization $organization, User $agent, OrganizationContext $context, SyncTeamMembers $syncMembers): void
    {
        $context->runAs($organization, function () use ($agent, $syncMembers): void {
            $team = Team::query()->create(['name' => 'Service Desk']);
            $syncMembers->handle($team, [$agent->id]);

            $queue = Queue::query()->create(['team_id' => $team->id, 'name' => 'First line', 'is_default' => true]);

            $hardware = Category::query()->create(['name' => 'Hardware', 'sort_order' => 1]);
            Category::query()->create(['name' => 'Laptops', 'parent_id' => $hardware->id]);
            Category::query()->create(['name' => 'Printers', 'parent_id' => $hardware->id]);
            $access = Category::query()->create(['name' => 'Access', 'sort_order' => 2]);

            Service::query()->create([
                'name' => 'New laptop',
                'description' => 'A laptop for a new colleague or a replacement for a broken one.',
                'category_id' => $hardware->id,
                'queue_id' => $queue->id,
                'impact' => Impact::Low,
                'urgency' => Urgency::Medium,
                'form_schema' => [
                    ['key' => 'model', 'label' => 'Model', 'type' => 'select', 'required' => true, 'options' => ['Standard 14"', 'Developer 16"']],
                    ['key' => 'needed_by', 'label' => 'Needed by', 'type' => 'date', 'required' => true],
                    ['key' => 'replacement', 'label' => 'This replaces a broken laptop', 'type' => 'checkbox'],
                ],
                'sort_order' => 1,
            ]);

            Service::query()->create([
                'name' => 'VPN access',
                'description' => 'Remote access to the company network.',
                'category_id' => $access->id,
                'impact' => Impact::Low,
                'urgency' => Urgency::Low,
                'form_schema' => [
                    ['key' => 'reason', 'label' => 'Why do you need it?', 'type' => 'textarea', 'required' => true],
                    ['key' => 'policy', 'label' => 'I have read the remote access policy', 'type' => 'checkbox', 'required' => true],
                ],
                'sort_order' => 2,
            ]);
        });
    }
}
