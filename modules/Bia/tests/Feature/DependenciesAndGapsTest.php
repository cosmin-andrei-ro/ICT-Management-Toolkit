<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Bia\Application\Actions\DecideAssessment;
use Modules\Bia\Application\Actions\ManageDependencies;
use Modules\Bia\Application\Actions\SaveAssessment;
use Modules\Bia\Application\Actions\StartAssessment;
use Modules\Bia\Application\Actions\SubmitAssessment;
use Modules\Bia\Application\Gaps\GapAnalysis;
use Modules\Bia\Application\Gaps\GapRow;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Bia\Domain\Models\Dependency;
use Modules\Bia\Http\Livewire\AssessmentPage;
use Modules\Bia\Http\Livewire\GapReport;
use Modules\Bia\Tests\Support\BiaScenario;
use Modules\Cmdb\Application\Actions\ManageRelationships;
use Modules\Cmdb\Domain\Enums\Criticality;
use Modules\Cmdb\Domain\Enums\RelationshipType;
use Modules\Cmdb\Domain\Models\CiLink;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Cmdb\Http\Livewire\ItemForm;
use Modules\Cmdb\Http\Livewire\ShowItem;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Tenancy\OrganizationContext;

beforeEach(function (): void {
    Notification::fake();
    $this->bia = BiaScenario::create();
    $type = CiType::factory()->create(['key' => 'application']);
    $this->app_ = ConfigurationItem::factory()->create(['type_id' => $type->id, 'name' => 'payroll-app', 'criticality' => 'low', 'recovery_time_minutes' => 480, 'recovery_point_minutes' => 60]);
    $this->db = ConfigurationItem::factory()->create(['type_id' => $type->id, 'name' => 'payroll-db', 'criticality' => 'medium', 'recovery_time_minutes' => 120, 'recovery_point_minutes' => 15]);
    $this->share = ConfigurationItem::factory()->create(['type_id' => $type->id, 'name' => 'file-share']);
    app(ManageRelationships::class)->add($this->app_, RelationshipType::DependsOn, $this->db);
});

/**
 * Approves an assessment of the process with these objectives and these items.
 *
 * @param  list<ConfigurationItem>  $items
 */
function approve(BiaScenario $bia, BusinessProcess $process, int $rto, ?int $rpo, array $items): Assessment
{
    $assessment = app(StartAssessment::class)->handle($process, $bia->owner);
    app(SaveAssessment::class)->handle($assessment, $bia->scores($rto <= 1440 ? 1440 : 4320), ['rto_minutes' => $rto, 'rpo_minutes' => $rpo]);

    foreach ($items as $item) {
        app(ManageDependencies::class)->linkItem($assessment, $item->id, $bia->owner);
    }

    app(SubmitAssessment::class)->handle($assessment, $bia->owner);
    app(DecideAssessment::class)->handle($assessment, $bia->coordinator, true, null);

    return $assessment->fresh();
}

it('lets the owner record what the process needs, audited, and only while the assessment is open', function (): void {
    $this->actingAs($this->bia->owner);
    $assessment = app(StartAssessment::class)->handle($this->bia->payroll, $this->bia->owner);
    $supplier = Supplier::factory()->create(['name' => 'Payroll Outsourcing SRL']);
    $site = Location::factory()->create(['name' => 'HQ']);
    $hr = BusinessProcess::factory()->create(['name' => 'HR records', 'owner_id' => $this->bia->owner->id]);
    $theirs = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => Supplier::factory()->create());

    $page = Livewire::test(AssessmentPage::class, ['assessment' => $assessment])
        ->set('itemSearch', 'payroll')->assertSee('payroll-app')
        ->call('linkItem', $this->app_->id)
        ->set('supplierId', $supplier->id)->call('addDependency', 'supplier')
        ->set('locationId', $site->id)->call('addDependency', 'location')
        ->set('upstreamId', $hr->id)->call('addDependency', 'process')
        ->set('roleName', 'Payroll specialist')->set('roleMinimum', '2')->call('addDependency', 'role')
        ->set('personId', $this->bia->deputy->id)->call('addDependency', 'person')
        ->assertHasNoErrors()
        ->assertSee('Payroll Outsourcing SRL')->assertSee('at least 2 people');

    $page->set('supplierId', $supplier->id)->call('addDependency', 'supplier')->assertHasErrors(['dependency_supplier']);
    $page->set('upstreamId', $this->bia->payroll->id)->call('addDependency', 'process')->assertHasErrors(['dependency_process']);
    $page->set('supplierId', $theirs->id)->call('addDependency', 'supplier')->assertHasErrors(['dependency_supplier']);
    // Add with nothing chosen: a message, not a database error.
    $page->set('supplierId', '')->call('addDependency', 'supplier')->assertHasErrors(['dependency_supplier']);
    $page->set('personId', 'not-a-uuid')->call('addDependency', 'person')->assertHasErrors(['dependency_person']);
    $page->set('roleName', '')->call('addDependency', 'role')->assertHasErrors(['dependency_role']);

    expect(Dependency::query()->where('assessment_id', $assessment->id)->count())->toBe(5)
        ->and(AuditLog::query()->where('auditable_id', $assessment->id)->whereNotNull('new_values->dependency_added')->count())->toBe(5)
        ->and(AuditLog::query()->where('auditable_id', $assessment->id)->whereNotNull('new_values->configuration_items_added')->exists())->toBeTrue();

    $page->set('scores', $this->bia->scores(1440))->set('rtoValue', '4')->set('rpoValue', '1')->call('submit')->assertHasNoErrors();
    Livewire::test(AssessmentPage::class, ['assessment' => $assessment])->call('linkItem', $this->share->id)->assertForbidden();
});

it('copies what the process needs into the next version', function (): void {
    $first = approve($this->bia, $this->bia->payroll, 240, 60, [$this->app_]);
    $role = new Dependency;
    $role->forceFill(['organization_id' => $first->organization_id, 'assessment_id' => $first->id, 'kind' => 'role', 'role_name' => 'Accountant'])->save();

    $second = app(StartAssessment::class)->handle($this->bia->payroll, $this->bia->owner);

    expect(Dependency::query()->where('assessment_id', $second->id)->value('role_name'))->toBe('Accountant')
        ->and(CiLink::query()->where('linkable_id', $second->id)->pluck('configuration_item_id')->all())->toBe([$this->app_->id]);
});

it('shows on the CMDB item page the processes that need it', function (): void {
    approve($this->bia, $this->bia->payroll, 240, 60, [$this->app_]);
    $this->actingAs($this->bia->lead);

    Livewire::test(ShowItem::class, ['item' => $this->app_])->assertSee('Business process: Payroll')->assertSee('RTO 4 hours');

    $this->actingAs($this->bia->agent);
    Livewire::test(ShowItem::class, ['item' => $this->app_])->assertDontSee('Business process: Payroll');
});

it('passes each process\'s objectives to the items it uses and to what they need, keeping the strictest', function (): void {
    approve($this->bia, $this->bia->payroll, 240, 30, [$this->app_]);
    $invoicing = BusinessProcess::factory()->create(['name' => 'Invoicing', 'owner_id' => $this->bia->owner->id]);
    approve($this->bia, $invoicing, 2880, 1440, [$this->db, $this->share]);
    // Not approved: does not count.
    $draft = BusinessProcess::factory()->create(['name' => 'Draft process', 'owner_id' => $this->bia->owner->id]);
    $open = app(StartAssessment::class)->handle($draft, $this->bia->owner);
    app(ManageDependencies::class)->linkItem($open, $this->share->id, $this->bia->owner);

    $rows = collect(app(GapAnalysis::class)->rows())->keyBy(fn (GapRow $row): string => $row->item->name);

    expect($rows['payroll-app']->requiredRto)->toBe(240)
        ->and($rows['payroll-app']->rtoStatus())->toBe(GapRow::GAP)
        ->and($rows['payroll-app']->rpoStatus())->toBe(GapRow::GAP)
        ->and($rows['payroll-db']->requiredRto)->toBe(240)
        ->and($rows['payroll-db']->requiredRpo)->toBe(30)
        ->and($rows['payroll-db']->status())->toBe(GapRow::MET)
        ->and(collect($rows['payroll-db']->processes)->pluck('direct', 'name')->all())->toBe(['Payroll' => false, 'Invoicing' => true])
        ->and($rows['file-share']->requiredRto)->toBe(2880)
        ->and($rows['file-share']->status())->toBe(GapRow::UNKNOWN)
        ->and($rows['payroll-app']->suggestedCriticality)->toBe('critical')
        ->and($rows['file-share']->suggestedCriticality)->toBe('medium')
        ->and(array_key_first($rows->all()))->toBe('payroll-app');
});

it('lets people who may change CMDB items align their criticality with the BIA', function (): void {
    approve($this->bia, $this->bia->payroll, 240, 30, [$this->app_]);

    $this->actingAs($this->bia->coordinator);
    Livewire::test(GapReport::class)->assertSee('payroll-app')->assertSee('BIA suggests Critical')->assertDontSee('Apply')
        ->call('alignCriticality', $this->app_->id)->assertForbidden();

    $this->actingAs($this->bia->lead);
    Livewire::test(GapReport::class)->assertSee('Apply')->call('alignCriticality', $this->app_->id);

    expect($this->app_->fresh()->criticality)->toBe(Criticality::Critical)
        ->and(AuditLog::query()->where('auditable_id', $this->app_->id)->where('new_values->criticality', 'critical')->exists())->toBeTrue();

    $this->actingAs($this->bia->agent)->get(route('bia.gaps'))->assertForbidden();
});

it('records what IT can deliver on the CMDB item', function (): void {
    $this->actingAs($this->bia->lead);

    Livewire::test(ItemForm::class, ['item' => $this->share])
        ->set('recoveryTimeValue', '1')->set('recoveryTimeUnit', 'days')
        ->set('recoveryPointValue', '4')->set('recoveryPointUnit', 'hours')
        ->call('save')->assertHasNoErrors();

    expect($this->share->fresh()->recovery_time_minutes)->toBe(1440)->and($this->share->fresh()->recovery_point_minutes)->toBe(240);
});
