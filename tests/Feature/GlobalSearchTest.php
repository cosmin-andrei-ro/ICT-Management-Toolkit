<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Itam\Domain\Models\License;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::query()->where('email', 'admin@example.com')->sole();
    $this->employee = User::query()->where('email', 'employee@example.com')->sole();
});

it('sends guests to sign in', function (): void {
    $this->get(route('core.search', ['q' => 'hv-01']))->assertRedirect(route('login'));
});

it('finds every kind of record, in the demo company', function (string $term, string $kind, string $title): void {
    app(OrganizationContext::class)->clear();

    $results = app(Modules\Core\Domain\Links\LinkedRecords::class);
    app(OrganizationContext::class)->set(Organization::query()->sole());
    $found = $results->searchEverywhere($term, $this->admin);

    expect($found)->toHaveKey($kind)
        ->and(collect($found[$kind])->pluck('title')->implode('|'))->toContain($title);
})->with([
    'configuration item' => ['hv-01', 'Configuration item', 'hv-01'],
    'asset' => ['hv-01', 'Asset', 'hv-01'],
    'licence' => ['Endpoint', 'Licence', 'Endpoint Protection'],
    'project' => ['CRM rollout', 'Project', 'CRM rollout'],
    'supplier' => ['Demo Hardware', 'Supplier', 'Demo Hardware'],
    'rack' => ['BUC-R01', 'Rack', 'BUC-R01'],
    'risk' => ['Ransomware', 'Risk', 'Ransomware'],
    'application' => ['Warehouse', 'Application', 'Warehouse'],
]);

it('shows the results on a page, with the search box on every page', function (): void {
    app(OrganizationContext::class)->set(Organization::query()->sole());
    $this->withoutMiddleware(Modules\Core\Http\Middleware\RequireTwoFactor::class)->actingAs($this->admin);

    $this->get(route('core.search', ['q' => 'hv-01']))->assertOk()->assertSee('name="q"', false)
        ->assertSee('Configuration item')->assertSee('hv-01');
    $this->get(route('core.search', ['q' => 'x']))->assertOk()->assertSee(__('core::search.too_short'));
    $this->get(route('core.search'))->assertOk()->assertSee(__('core::search.empty'));
    $this->get(route('core.search', ['q' => '<script>alert(1)</script>']))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    $this->get(route('core.search', ['q' => ['a', 'b']]))->assertOk();
});

it('shows a person only what they may see', function (): void {
    app(OrganizationContext::class)->set(Organization::query()->sole());
    $records = app(Modules\Core\Domain\Links\LinkedRecords::class);

    expect(array_keys($records->searchEverywhere('hv-01', $this->employee)))->not->toContain('Configuration item', 'Asset')
        ->and(array_keys($records->searchEverywhere('Ransomware', $this->employee)))->not->toContain('Risk')
        ->and(array_keys($records->searchEverywhere('CRM rollout', $this->employee)))->not->toContain('Project');
});

it('keeps organizations apart', function (): void {
    $other = Organization::factory()->create();
    $this->admin->organizations()->attach($other);
    app(OrganizationContext::class)->set($other);

    expect(app(Modules\Core\Domain\Links\LinkedRecords::class)->searchEverywhere('hv-01', $this->admin))->toBe([]);
});

it('never finds a licence by its key', function (): void {
    app(OrganizationContext::class)->set(Organization::query()->sole());
    $license = License::query()->firstOrFail();
    $license->forceFill(['license_key' => 'SECRETKEY-12345'])->saveQuietly();

    expect(app(Modules\Core\Domain\Links\LinkedRecords::class)->searchEverywhere('SECRETKEY', $this->admin))->toBe([]);
});

it('does not add the new kinds to what can be picked as evidence', function (): void {
    $kinds = app(Modules\Core\Domain\Links\LinkedRecords::class)->searchable();

    expect($kinds)->not->toHaveKey('cmdb.configuration_item')->not->toHaveKey('itam.asset')->not->toHaveKey('core.supplier');
});
