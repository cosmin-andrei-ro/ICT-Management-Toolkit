<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Cmdb\Domain\Models\Relationship;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\License;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Models\SlaTimer;
use Modules\Ticketing\Domain\Models\Ticket;

it('builds the demo company with an account per role and three months of history', function (): void {
    $this->seed(DatabaseSeeder::class);

    $organization = Organization::query()->where('slug', 'demo')->sole();
    app(OrganizationContext::class)->set($organization);

    // Every role can be tried with its own account, two-factor ready for staff.
    foreach (DefaultRole::cases() as $role) {
        expect(User::query()->role($role->value)->exists())->toBeTrue($role->value);
    }
    expect(User::query()->where('email', 'agent@example.com')->sole()->two_factor_confirmed_at)->not->toBeNull();

    // The service desk over 90 days, at every stage, with late SLAs.
    $tickets = Ticket::query()->get();
    expect($tickets->where('type', TicketType::Incident)->count() + $tickets->where('type', TicketType::ServiceRequest)->count())->toBe(60)
        ->and($tickets->min('created_at')->lt(now()->subDays(80)))->toBeTrue()
        ->and($tickets->where('status', TicketStatus::Closed)->count())->toBeGreaterThan(30)
        ->and($tickets->whereIn('status', [TicketStatus::New, TicketStatus::InProgress, TicketStatus::Pending])->count())->toBeGreaterThan(3)
        ->and($tickets->where('type', TicketType::Problem)->count())->toBe(2)
        ->and($tickets->where('type', TicketType::Change)->count())->toBe(4)
        ->and(SlaTimer::query()->whereNotNull('breached_at')->exists())->toBeTrue()
        ->and($tickets->every(fn (Ticket $ticket): bool => $ticket->created_at->lte(now())))->toBeTrue();

    // The estate, linked: assets are what CMDB items describe.
    expect(ConfigurationItem::query()->count())->toBe(20)
        ->and(Relationship::query()->count())->toBe(20)
        ->and(Asset::query()->count())->toBeGreaterThan(30)
        ->and(app(ConfigurationItems::class)->linkedTo(Asset::query()->where('name', 'hv-01')->sole()))->toHaveCount(1)
        ->and(License::query()->where('expires_on', '<=', today()->addDays(60))->exists())->toBeTrue();
});

it('refuses to seed demo data outside local and testing', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    // Called directly: in production db:seed stops at its own confirmation prompt first.
    expect(fn () => app()->call([app(DatabaseSeeder::class), 'run']))->toThrow(RuntimeException::class, 'local or testing');
});
