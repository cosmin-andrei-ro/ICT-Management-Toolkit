<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Bcm\Application\Notifications\PlanUpdate;
use Modules\Bcm\Domain\Enums\VersionStatus;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bcm\Domain\Models\PlanVersion;
use Modules\Bcm\Domain\Models\Step;
use Modules\Bcm\Http\Livewire\PlanForm;
use Modules\Bcm\Http\Livewire\PlanIndex;
use Modules\Bcm\Http\Livewire\PlanVersionPage;
use Modules\Bcm\Http\Livewire\ShowPlan;
use Modules\Bcm\Tests\Support\BcmScenario;
use Modules\Cmdb\Domain\Models\CiLink;
use Modules\Cmdb\Http\Livewire\ShowItem;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;

beforeEach(function (): void {
    Notification::fake();
    $this->bcm = BcmScenario::create();
});

/**
 * The owner writes and submits a complete DRP for payroll: 1 hour to activate, 2 to recover.
 */
function writePayrollPlan(BcmScenario $bcm): PlanVersion
{
    $plan = Plan::factory()->create(['name' => 'Payroll DRP', 'owner_id' => $bcm->owner->id]);
    test()->actingAs($bcm->owner);
    Livewire::test(ShowPlan::class, ['plan' => $plan])->call('startVersion');
    $version = PlanVersion::query()->where('plan_id', $plan->id)->sole();

    Livewire::test(PlanVersionPage::class, ['version' => $version])
        ->call('addProcess', $bcm->payroll->id)
        ->call('linkItem', $bcm->payrollApp->id)
        ->set('scenarios', ['it_outage', 'cyber_attack'])
        ->set('activationCriteria', 'Payroll application down for more than 1 hour on a working day')
        ->set('memberRole', 'Incident manager')->set('memberUserId', $bcm->coordinator->id)->call('addMember')
        ->set('memberRole', 'Vendor support')->set('memberContactName', 'Sample hotline')->set('memberContactPhone', '+40 21 000 0000')->call('addMember')
        ->call('editStep')->set('stepPhase', 'activation')->set('stepAction', 'Declare the incident')->set('stepDurationValue', '1')->set('stepDurationUnit', 'hours')->call('saveStep')
        ->call('editStep')->set('stepPhase', 'recovery')->set('stepAction', 'Restore the database from backup')->set('stepDurationValue', '2')->set('stepDurationUnit', 'hours')->set('stepItemId', $bcm->payrollApp->id)->call('saveStep')
        ->assertSee('Within the strictest RTO')
        ->call('submit')
        ->assertHasNoErrors();

    return $version->fresh();
}

it('lets the coordinator write any plan, team leads only recovery plans, and agents read them', function (): void {
    $this->actingAs($this->bcm->coordinator);
    Livewire::test(PlanForm::class)->set('type', 'bcp')->set('name', 'Head office loss')->set('ownerId', $this->bcm->owner->id)->call('save')->assertHasNoErrors();

    $this->actingAs($this->bcm->lead);
    Livewire::test(PlanForm::class)->assertSet('type', 'drp')
        ->set('type', 'bcp')->set('name', 'Sneaky BCP')->set('ownerId', $this->bcm->lead->id)->call('save')->assertHasErrors(['type'])
        ->set('type', 'drp')->set('name', 'Data centre DRP')->call('save')->assertHasNoErrors();

    $this->actingAs($this->bcm->agent);
    Livewire::test(PlanIndex::class)->assertSee('Head office loss')->assertSee('Data centre DRP');
    $this->get(route('bcm.plans.create'))->assertForbidden();
    $this->actingAs($this->bcm->employee)->get(route('bcm.plans.index'))->assertForbidden();
});

it('compares the runbook with the strictest RTO of the processes covered, and requires a complete plan', function (): void {
    $plan = Plan::factory()->create(['owner_id' => $this->bcm->owner->id]);
    $this->actingAs($this->bcm->owner);
    Livewire::test(ShowPlan::class, ['plan' => $plan])->call('startVersion');
    $version = PlanVersion::query()->sole();

    Livewire::test(PlanVersionPage::class, ['version' => $version])
        ->call('submit')
        ->assertHasErrors(['scope', 'scenarios', 'activationCriteria', 'team', 'steps'])
        ->call('addProcess', $this->bcm->payroll->id)
        ->call('editStep')->set('stepPhase', 'recovery')->set('stepAction', 'Rebuild the server')->set('stepDurationValue', '1')->set('stepDurationUnit', 'days')->call('saveStep')
        ->assertSee('Longer than the strictest RTO of the processes covered (4 hours, Payroll).')
        ->call('editStep')->set('stepPhase', 'restoration')->set('stepAction', 'Move back')->set('stepDurationValue', '5')->set('stepDurationUnit', 'days')->call('saveStep')
        ->assertSee('Activation and recovery take about 1 day.')
        ->set('memberRole', 'Nobody')->call('addMember')->assertHasErrors(['team']);
});

it('sends a submitted plan to the coordinator, who approves it into force', function (): void {
    $version = writePayrollPlan($this->bcm);

    expect($version->status)->toBe(VersionStatus::Submitted)
        ->and(AuditLog::query()->where('auditable_id', $version->id)->whereNotNull('new_values->step_added')->count())->toBe(2);
    Notification::assertSentTo($this->bcm->coordinator, PlanUpdate::class, fn (PlanUpdate $mail): bool => $mail->kind === 'submitted');

    Livewire::test(PlanVersionPage::class, ['version' => $version])->call('approve')->assertForbidden();
    $this->actingAs($this->bcm->lead);
    Livewire::test(PlanVersionPage::class, ['version' => $version])->call('approve')->assertForbidden();

    $this->actingAs($this->bcm->coordinator);
    Livewire::test(PlanVersionPage::class, ['version' => $version])->call('sendBack')->assertHasErrors(['comment']);
    Livewire::test(PlanVersionPage::class, ['version' => $version])->call('approve')->assertHasNoErrors();

    $plan = $version->plan->fresh();
    expect($version->fresh()->status)->toBe(VersionStatus::Approved)
        ->and($plan->approved_version)->toBe(1)
        ->and($plan->next_review_on?->toDateString())->toBe(now()->addYear()->toDateString());
    Notification::assertSentTo($this->bcm->owner, PlanUpdate::class, fn (PlanUpdate $mail): bool => $mail->kind === 'approved');

    $this->actingAs($this->bcm->owner);
    Livewire::test(PlanVersionPage::class, ['version' => $version])->call('save')->assertForbidden();
});

it('starts a review from the approved version with its scope, team and runbook', function (): void {
    $first = writePayrollPlan($this->bcm);
    $this->actingAs($this->bcm->coordinator);
    Livewire::test(PlanVersionPage::class, ['version' => $first])->call('approve');

    $this->actingAs($this->bcm->owner);
    Livewire::test(ShowPlan::class, ['plan' => $first->plan])->call('startVersion');
    $second = PlanVersion::query()->where('version', 2)->sole();

    expect($second->processIds())->toBe([$this->bcm->payroll->id])
        ->and($second->team()->count())->toBe(2)
        ->and($second->steps()->pluck('action')->all())->toBe(['Declare the incident', 'Restore the database from backup'])
        ->and($second->scenarios)->toBe(['it_outage', 'cyber_attack'])
        ->and(CiLink::query()->where('linkable_id', $second->id)->pluck('configuration_item_id')->all())->toBe([$this->bcm->payrollApp->id]);

    $last = Step::query()->where('version_id', $second->id)->where('position', 2)->sole();
    Livewire::test(PlanVersionPage::class, ['version' => $second])->call('moveStepUp', $last->id)->call('removeStep', Step::query()->where('version_id', $second->id)->where('position', 2)->sole()->id);
    expect($second->steps()->get(['position', 'action'])->toArray())->toBe([['position' => 1, 'action' => 'Restore the database from backup']]);
});

it('shows plans only to the people allowed, and lists them on the CMDB item', function (): void {
    $version = writePayrollPlan($this->bcm);
    $theirs = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => Plan::factory()->create());

    $this->actingAs($this->bcm->employee)->get(route('bcm.plans.show', $version->plan))->assertForbidden();
    $this->actingAs($this->bcm->employee)->get(route('bcm.versions.show', $version))->assertForbidden();
    $this->actingAs($this->bcm->owner)->get(route('home'))->assertSee('My plans');
    $this->actingAs($this->bcm->coordinator)->get(route('bcm.plans.show', $theirs))->assertNotFound();

    $this->actingAs($this->bcm->agent);
    Livewire::test(ShowItem::class, ['item' => $this->bcm->payrollApp])->assertSee('IT disaster recovery plan: Payroll DRP');
});

it('prints the plan in force for offline use', function (): void {
    $version = writePayrollPlan($this->bcm);
    $this->actingAs($this->bcm->owner)->get(route('bcm.plans.sheet', $version->plan))->assertNotFound();

    $this->actingAs($this->bcm->coordinator);
    Livewire::test(PlanVersionPage::class, ['version' => $version])->call('approve');

    $this->actingAs($this->bcm->agent)->get(route('bcm.plans.sheet', $version->plan))
        ->assertOk()
        ->assertSee('Payroll DRP')->assertSee('Restore the database from backup')->assertSee('Sample hotline')->assertSee('+40 21 000 0000')
        ->assertSee('Required: 4 hours (Payroll).');

    config(['core.pdf.gotenberg_url' => 'http://gotenberg:3000']);
    Http::fake(['gotenberg:3000/*' => Http::response('%PDF-1.7 fake', 200)]);
    $this->get(route('bcm.plans.sheet.pdf', $version->plan))->assertOk()->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename=payroll-drp-v1.pdf');
    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->body(), 'Restore the database from backup'));
    $this->actingAs($this->bcm->employee)->get(route('bcm.plans.sheet.pdf', $version->plan))->assertForbidden();
});
