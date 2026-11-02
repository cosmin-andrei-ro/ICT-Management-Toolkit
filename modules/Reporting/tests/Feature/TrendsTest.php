<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Reporting\Application\Actions\TakeSnapshots;
use Modules\Reporting\Domain\Models\MetricSnapshot;
use Modules\Reporting\Http\Livewire\Dashboard;
use Modules\Reporting\Http\Livewire\MetricTrend;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Models\Ticket;

beforeEach(function (): void {
    app(SyncPermissions::class)->handle();
    $this->organization = Organization::factory()->create();
    app(OrganizationContext::class)->set($this->organization);
});

function trendsMember(Organization $organization, DefaultRole $role): User
{
    $factory = User::factory()->memberOf($organization);
    $user = ($role === DefaultRole::Requester ? $factory : $factory->withTwoFactor())->create();
    app(OrganizationContext::class)->runAs($organization, fn () => $user->assignRole($role->value));

    return User::query()->findOrFail($user->id);
}

it('records every indicator of every organization once a day', function (): void {
    Ticket::factory()->count(3)->create(['status' => TicketStatus::New]);
    $other = Organization::factory()->create();
    app(OrganizationContext::class)->runAs($other, fn () => Ticket::factory()->create(['organization_id' => $other->id, 'status' => TicketStatus::New]));

    $this->artisan('reporting:snapshot')->assertSuccessful();
    // A second run the same day replaces the values.
    Ticket::factory()->create(['status' => TicketStatus::New]);
    app(TakeSnapshots::class)->handle();

    $values = MetricSnapshot::query()->withoutGlobalScopes()->where('metric_key', 'ticketing.open')->pluck('value', 'organization_id');
    expect($values)->toHaveCount(2)
        ->and((float) $values[$this->organization->id])->toBe(4.0)
        ->and((float) $values[$other->id])->toBe(1.0)
        ->and(MetricSnapshot::query()->where('taken_on', today())->count())->toBeGreaterThan(20);
});

it('forgets values older than the retention period', function (): void {
    config(['reporting.snapshots.keep_days' => 30]);
    MetricSnapshot::factory()->create(['taken_on' => today()->subDays(31)]);
    $kept = MetricSnapshot::factory()->create(['taken_on' => today()->subDays(30)]);

    app(TakeSnapshots::class)->handle();

    expect(MetricSnapshot::query()->where('metric_key', 'ticketing.open')->where('taken_on', '<', today())->pluck('id')->all())->toBe([$kept->id]);
});

it('shows an indicator over 30, 90 and 365 days to the people who may see it', function (): void {
    $agent = trendsMember($this->organization, DefaultRole::Agent);
    $today = CarbonImmutable::today();

    foreach ([[100, 9], [40, 7], [2, 5], [0, 3]] as [$daysAgo, $value]) {
        MetricSnapshot::factory()->create(['taken_on' => $today->subDays($daysAgo), 'value' => $value]);
    }

    $this->actingAs($agent)->get(route('reporting.metrics.show', 'ticketing.open'))->assertOk()
        ->assertSee(__('ticketing::ui.metrics.open'))
        ->assertSee('<svg', false);

    $page = Livewire::test(MetricTrend::class, ['metric' => 'ticketing.open']);
    expect($page->viewData('summary'))->toBe(['first' => 5.0, 'lowest' => 3.0, 'highest' => 5.0, 'average' => 4.0]);

    $page->call('period', 365);
    expect($page->viewData('summary'))->toMatchArray(['first' => 9.0, 'highest' => 9.0])
        ->and($page->viewData('series'))->toHaveCount(365);

    // Only the periods offered.
    $page->call('period', 7)->assertSet('days', 30);
});

it('refuses indicators the person may not see, and unknown ones', function (): void {
    $agent = trendsMember($this->organization, DefaultRole::Agent);

    $this->actingAs($agent)->get(route('reporting.metrics.show', 'audit.major_open'))->assertForbidden();
    $this->actingAs($agent)->get(route('reporting.metrics.show', 'ticketing.nothing'))->assertNotFound();
    $this->get(route('reporting.metrics.show', 'ticketing.open'))->assertOk();

    auth()->logout();
    $this->get(route('reporting.metrics.show', 'ticketing.open'))->assertRedirect(route('login'));
});

it('never shows another organization\'s values', function (): void {
    $agent = trendsMember($this->organization, DefaultRole::Agent);
    $other = Organization::factory()->create();
    app(OrganizationContext::class)->runAs($other, fn () => MetricSnapshot::factory()->create(['organization_id' => $other->id, 'value' => 42]));

    $this->actingAs($agent);
    expect(Livewire::test(MetricTrend::class, ['metric' => 'ticketing.open'])->viewData('summary'))->toBeNull();
});

it('draws a 30-day line on the dashboard with a link to the trend', function (): void {
    $lead = trendsMember($this->organization, DefaultRole::TeamLead);
    MetricSnapshot::factory()->create(['taken_on' => today()->subDay(), 'value' => 4]);
    MetricSnapshot::factory()->create(['taken_on' => today(), 'value' => 6]);

    $this->actingAs($lead);
    Livewire::test(Dashboard::class)
        ->assertSeeHtml('aria-label="'.e(__('reporting::trends.trend_of', ['metric' => __('ticketing::ui.metrics.open')])).'"')
        ->assertSeeHtml('href="'.e(route('reporting.metrics.show', 'ticketing.open')).'"');
});
