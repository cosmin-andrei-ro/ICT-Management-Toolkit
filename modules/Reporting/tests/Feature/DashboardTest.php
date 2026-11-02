<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Metrics\Metric;
use Modules\Core\Domain\Metrics\Metrics;
use Modules\Core\Domain\Metrics\MetricSource;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Reporting\Domain\Models\DashboardPreference;
use Modules\Reporting\Http\Livewire\Dashboard;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Models\Ticket;

beforeEach(function (): void {
    app(SyncPermissions::class)->handle();
    $this->organization = Organization::factory()->create();
    app(OrganizationContext::class)->set($this->organization);
});

function dashboardMember(Organization $organization, DefaultRole $role): User
{
    $factory = User::factory()->memberOf($organization);
    $user = ($role === DefaultRole::Requester ? $factory : $factory->withTwoFactor())->create();
    app(OrganizationContext::class)->runAs($organization, fn () => $user->assignRole($role->value));

    return User::query()->findOrFail($user->id);
}

it('shows on the home page only the indicators the person may see', function (): void {
    $agent = dashboardMember($this->organization, DefaultRole::Agent);

    $this->actingAs($agent)->get(route('home'))->assertOk()
        ->assertSee(__('reporting::dashboard.title'))
        ->assertSee(__('ticketing::ui.metrics.open'))
        ->assertDontSee(__('risk::risks.metrics.overdue_actions'))
        ->assertDontSee(__('audit::compliance.metrics.major_open'));

    $compliance = dashboardMember($this->organization, DefaultRole::ComplianceManager);
    $this->actingAs($compliance)->get(route('home'))->assertOk()
        ->assertSee(__('audit::compliance.metrics.major_open'))
        ->assertDontSee(__('ticketing::ui.metrics.sla_breached'));
});

it('shows no dashboard to people without any indicator', function (): void {
    $requester = dashboardMember($this->organization, DefaultRole::Requester);

    $this->actingAs($requester)->get(route('home'))->assertOk()
        ->assertSee(__('core::layout.welcome', ['name' => $requester->name]))
        ->assertDontSee(__('reporting::dashboard.customise'));
});

it('counts only the records of the current organization and links to the filtered list', function (): void {
    $agent = dashboardMember($this->organization, DefaultRole::Agent);
    Ticket::factory()->count(2)->create(['status' => TicketStatus::New]);
    Ticket::factory()->create(['status' => TicketStatus::Cancelled]);

    $other = Organization::factory()->create();
    app(OrganizationContext::class)->runAs($other, fn () => Ticket::factory()->count(5)->create(['organization_id' => $other->id, 'status' => TicketStatus::New]));

    $this->actingAs($agent);
    $groups = app(Metrics::class)->visibleTo($agent);
    $desk = collect($groups)->firstWhere('source.key', 'ticketing');
    $values = collect($desk['metrics'])->mapWithKeys(fn (array $row): array => [$row['metric']->key => $row['value']]);

    expect($values['ticketing.open'])->toBe(2)
        ->and($values['ticketing.unassigned'])->toBe(2);

    Livewire::test(Dashboard::class)
        ->assertSeeHtml('href="'.e(route('ticketing.desk.index', ['assignee' => 'unassigned'])).'"');
});

it('lets people hide, show and reorder what they see, and restore the defaults', function (): void {
    $lead = dashboardMember($this->organization, DefaultRole::TeamLead);
    $this->actingAs($lead);

    $component = Livewire::test(Dashboard::class)
        ->call('toggleCustomising')
        ->call('hide', 'ticketing.open')
        ->call('moveDown', 'ticketing');

    $preference = DashboardPreference::query()->where('user_id', $lead->id)->sole();
    expect($preference->hidden_metrics)->toBe(['ticketing.open'])
        ->and($preference->source_order[0])->not->toBe('ticketing')
        ->and($preference->source_order[1])->toBe('ticketing');

    // Hidden indicators stay visible, greyed, while customising - and are gone afterwards.
    $component->assertSeeHtml('wire:key="metric-ticketing.open"')
        ->call('toggleCustomising')
        ->assertDontSeeHtml('wire:key="metric-ticketing.open"')
        ->assertSeeHtml('wire:key="metric-ticketing.unassigned"');

    $component->call('toggleCustomising')->call('show', 'ticketing.open');
    expect($preference->fresh()->hidden_metrics)->toBe([]);

    $component->call('restoreDefaults');
    expect(DashboardPreference::query()->where('user_id', $lead->id)->exists())->toBeFalse();
});

it('refuses indicators and groups the person may not see', function (): void {
    $agent = dashboardMember($this->organization, DefaultRole::Agent);
    $this->actingAs($agent);

    Livewire::test(Dashboard::class)->call('hide', 'audit.major_open')->assertHasErrors('metric');
    Livewire::test(Dashboard::class)->call('hide', 'nothing.here')->assertHasErrors('metric');
    Livewire::test(Dashboard::class)->call('moveUp', 'audit')->assertHasErrors('source');

    expect(DashboardPreference::query()->exists())->toBeFalse();
});

it('keeps each person\'s arrangement to themselves and to the organization', function (): void {
    $lead = dashboardMember($this->organization, DefaultRole::TeamLead);
    $colleague = dashboardMember($this->organization, DefaultRole::TeamLead);

    $this->actingAs($lead);
    Livewire::test(Dashboard::class)->call('hide', 'ticketing.open');

    $this->actingAs($colleague);
    Livewire::test(Dashboard::class)->assertSeeHtml('wire:key="metric-ticketing.open"');

    $other = Organization::factory()->create();
    app(OrganizationContext::class)->set($other);
    expect(DashboardPreference::query()->count())->toBe(0);
});

it('registers indicators with a translated label, an existing link and a computable value', function (): void {
    $metrics = app(Metrics::class);

    expect($metrics->sources())->not->toBeEmpty();

    foreach ($metrics->sources() as $source) {
        expect(__($source->label))->not->toBe($source->label);

        foreach ($source->metrics as $metric) {
            expect(__($metric->label))->not->toBe($metric->label, $metric->key)
                ->and($metric->route === null || Route::has($metric->route))->toBeTrue($metric->key);
        }
    }

    // Every source's query runs against an empty organization.
    expect(array_keys($metrics->all()))->toContain('ticketing.open', 'itam.licenses_expiring', 'bia.never_assessed', 'bcm.uncovered', 'applications.not_documented', 'risk.overdue_actions', 'audit.major_open', 'documentation.review_overdue');
});

it('rejects indicators that do not belong to their source', function (): void {
    app(Metrics::class)->register(new MetricSource('demo', 'Demo', [new Metric('other.count', 'Count', 'demo.view')], fn (): array => []));
})->throws(InvalidArgumentException::class);
