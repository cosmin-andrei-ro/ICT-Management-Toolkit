<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Risk\Application\Catalog\ProvisionCatalog;
use Modules\Risk\Domain\Models\CatalogEntry;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\RiskSettings;
use Modules\Risk\Http\Filament\Pages\RiskSettingsPage;
use Modules\Risk\Http\Filament\Resources\CatalogEntries\Pages\ManageCatalogEntries;
use Modules\Risk\Http\Livewire\MyRisks;
use Modules\Risk\Http\Livewire\RiskForm;
use Modules\Risk\Http\Livewire\RiskIndex;
use Modules\Risk\Http\Livewire\ShowRisk;
use Modules\Risk\Tests\Support\RiskScenario;

beforeEach(function (): void {
    $this->risks = RiskScenario::create();
});

function registerRisk(RiskScenario $risks, string $title): Risk
{
    test()->actingAs($risks->manager);
    Livewire::test(RiskForm::class)
        ->set('title', $title)->set('category', 'information_security')->set('ownerId', $risks->owner->id)
        ->call('save')->assertHasNoErrors();

    return Risk::query()->where('title', $title)->sole();
}

it('lets risk managers register risks, owners assess theirs, and the register\'s readers only read', function (): void {
    $risk = registerRisk($this->risks, 'Ransomware encrypts the payroll database');
    $second = registerRisk($this->risks, 'Payroll vendor stops its service');
    expect($risk->code())->toBe('R-0001')->and($second->code())->toBe('R-0002')
        ->and($risk->next_review_on?->toDateString())->toBe(now()->addYear()->toDateString());

    // The owner, a plain employee, finds and assesses the risk (the catalog starts with its defaults).
    $this->actingAs($this->risks->owner);
    Livewire::test(MyRisks::class)->assertSee('R-0001');
    app(ProvisionCatalog::class)->handle();
    $ransomware = CatalogEntry::query()->where('name', 'Ransomware and other malware')->sole();
    $backup = CatalogEntry::query()->where('name', 'No tested backup')->sole();
    Livewire::test(ShowRisk::class, ['risk' => $risk])
        ->set('likelihood', '4')->set('impact', '5')
        ->set('entryIds', [$ransomware->id, $backup->id])
        ->set('controls', 'Endpoint protection')
        ->call('assess')->assertHasNoErrors()
        ->assertSee('Critical · 20');

    $risk->refresh();
    expect($risk->inherentScore())->toBe(20)->and($risk->catalogEntries()->pluck('name')->sort()->values()->all())->toBe(['No tested backup', 'Ransomware and other malware']);
    expect(AuditLog::query()->where('auditable_id', $risk->id)->count())->toBeGreaterThan(2);

    $this->actingAs($this->risks->owner);
    Livewire::test(RiskForm::class, ['risk' => $risk])->assertForbidden();

    $this->actingAs($this->risks->lead);
    Livewire::test(RiskIndex::class)->assertSee('R-0001')->assertDontSee('New risk');
    Livewire::test(ShowRisk::class, ['risk' => $risk])->set('likelihood', '1')->call('assess')->assertForbidden();

    $this->actingAs($this->risks->employee);
    $this->get(route('risk.risks.show', $risk))->assertForbidden();
    $this->get(route('risk.index'))->assertForbidden();
});

it('links a risk to applications, CMDB items, processes and suppliers of the organization only', function (): void {
    $risk = registerRisk($this->risks, 'Payroll unavailable at month end');
    app(OrganizationContext::class)->set(Organization::factory()->create());
    $foreign = Supplier::factory()->create(['name' => 'Payroll Foreign Ltd']);
    app(OrganizationContext::class)->set($this->risks->organization);

    Livewire::test(ShowRisk::class, ['risk' => $risk])
        ->set('assetType', 'application')->set('assetSearch', 'Pay')->assertSee('APP-PAY')
        ->call('addAsset', 'application', $this->risks->payrollApp->id)
        ->call('addAsset', 'configuration_item', $this->risks->payrollDb->id)
        ->call('addAsset', 'process', $this->risks->payroll->id)
        ->call('addAsset', 'supplier', $this->risks->vendor->id)
        ->call('addAsset', 'supplier', $foreign->id)->assertHasErrors('assetSearch')
        ->assertSee('Payroll')->assertSee('PAYDB01')->assertSee('Monthly payroll')->assertSee('Payroll Outsourcing SRL')
        ->call('removeAsset', 'supplier', $this->risks->vendor->id)
        ->assertDontSee('Payroll Outsourcing SRL');

    expect(collect($risk->assets())->pluck('type')->sort()->values()->all())->toBe(['application', 'configuration_item', 'process']);
});

it('shows the open risks on the heat map and filters by cell and level', function (): void {
    foreach ([['Critical one', 5, 4], ['High one', 3, 4], ['Low one', 1, 2]] as [$title, $likelihood, $impact]) {
        Risk::factory()->create(['title' => $title, 'inherent_likelihood' => $likelihood, 'inherent_impact' => $impact]);
    }
    Risk::factory()->create(['title' => 'Closed one', 'status' => 'closed', 'inherent_likelihood' => 5, 'inherent_impact' => 4]);

    $this->actingAs($this->risks->manager);
    Livewire::test(RiskIndex::class)
        ->assertSeeHtml('aria-label="Likelihood 5, impact 4: 1 risk(s)"')
        ->assertSee('Critical one')->assertSee('Low one')->assertDontSee('Closed one')
        ->call('selectCell', 3, 4)->assertSee('High one')->assertDontSee('Critical one')
        ->call('selectCell', 3, 4)->set('level', 'critical')->assertSee('Critical one')->assertDontSee('High one')
        ->set('level', '')->set('closed', true)->assertSee('Closed one');
});

it('lets risk managers tune the method and the catalog', function (): void {
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->risks->manager);
    $this->get('/admin/risk/method')->assertOk();

    Livewire::test(RiskSettingsPage::class)
        ->fillForm(['medium_from' => 6, 'high_from' => 6])->call('save')->assertHasFormErrors(['high_from'])
        ->fillForm(['medium_from' => 4, 'high_from' => 8, 'critical_from' => 15, 'approval_from' => 8, 'review_months' => 6])
        ->set('data.impact_levels.4.label', 'Catastrophic')
        ->call('save')->assertHasNoFormErrors();

    $settings = RiskSettings::current();
    expect($settings->level(15)->value)->toBe('critical')->and($settings->review_months)->toBe(6)->and($settings->levelLabel('impact', 5))->toBe('Catastrophic');

    $this->get('/admin/risk/catalog')->assertOk();
    expect(CatalogEntry::query()->count())->toBe(38);
    Livewire::test(ManageCatalogEntries::class)
        ->callAction('create', data: ['kind' => 'threat', 'name' => 'Drone over the data center', 'is_active' => true])->assertHasNoFormErrors()
        ->callAction('create', data: ['kind' => 'threat', 'name' => 'drone over the data center', 'is_active' => true])->assertHasFormErrors(['name']);

    $risk = Risk::factory()->create();
    $used = CatalogEntry::query()->where('name', 'Fire')->sole();
    $risk->catalogEntries()->attach($used->id, ['organization_id' => $risk->organization_id]);
    Livewire::test(ManageCatalogEntries::class)->assertTableActionHidden('delete', $used);

    $this->actingAs($this->risks->lead)->get('/admin/risk/method')->assertForbidden();
});
