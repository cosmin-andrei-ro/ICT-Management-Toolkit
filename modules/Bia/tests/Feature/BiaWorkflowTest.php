<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Bia\Application\Notifications\AssessmentUpdate;
use Modules\Bia\Domain\Enums\AssessmentStatus;
use Modules\Bia\Domain\Enums\Tier;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Bia\Domain\Models\ImpactCategory;
use Modules\Bia\Domain\Models\TimeHorizon;
use Modules\Bia\Http\Livewire\AssessmentPage;
use Modules\Bia\Http\Livewire\MyProcesses;
use Modules\Bia\Http\Livewire\ProcessForm;
use Modules\Bia\Http\Livewire\ProcessIndex;
use Modules\Bia\Http\Livewire\ShowProcess;
use Modules\Bia\Tests\Support\BiaScenario;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

beforeEach(function (): void {
    Notification::fake();
    $this->bia = BiaScenario::create();
});

/**
 * The owner opens and fills in a complete assessment of payroll: unacceptable from 1 day, RTO 4 hours.
 */
function ownerSubmitsPayroll(BiaScenario $bia): Assessment
{
    test()->actingAs($bia->owner);
    Livewire::test(ShowProcess::class, ['process' => $bia->payroll])->call('startAssessment');
    $assessment = Assessment::query()->where('process_id', $bia->payroll->id)->sole();

    Livewire::test(AssessmentPage::class, ['assessment' => $assessment])
        ->set('scores', $bia->scores(1440))
        ->set('rtoValue', '4')->set('rtoUnit', 'hours')
        ->set('rpoValue', '1')->set('rpoUnit', 'days')
        ->set('workarounds', 'Paper timesheets')
        ->call('submit')
        ->assertHasNoErrors();

    return $assessment->fresh();
}

it('lets the coordinator create processes owned by members, and keeps the list from employees and agents', function (): void {
    $this->actingAs($this->bia->coordinator);

    Livewire::test(ProcessForm::class)
        ->set('name', 'payroll')->set('ownerId', $this->bia->owner->id)->call('save')->assertHasErrors(['name'])
        ->set('name', 'Invoicing')->set('ownerId', User::factory()->memberOf(Organization::factory()->create())->create()->id)->call('save')->assertHasErrors(['ownerId'])
        ->set('ownerId', $this->bia->owner->id)->set('deputyId', $this->bia->owner->id)->call('save')->assertHasErrors(['deputyId'])
        ->set('deputyId', '')->call('save')->assertHasNoErrors();

    expect(BusinessProcess::query()->where('name', 'Invoicing')->sole()->owner_id)->toBe($this->bia->owner->id);

    Livewire::test(ProcessIndex::class)->assertSee('Payroll')->assertSee('Invoicing');
    $this->actingAs($this->bia->lead)->get(route('bia.processes.index'))->assertOk();
    $this->actingAs($this->bia->agent)->get(route('bia.processes.index'))->assertForbidden();
    $this->actingAs($this->bia->owner)->get(route('bia.processes.create'))->assertForbidden();
});

it('lets the owner assess their process, computes the MTPD and checks the RTO against it', function (): void {
    $this->actingAs($this->bia->owner);
    Livewire::test(ShowProcess::class, ['process' => $this->bia->payroll])->call('startAssessment')->assertRedirect();
    $assessment = Assessment::query()->sole();

    $page = Livewire::test(AssessmentPage::class, ['assessment' => $assessment])
        ->set('scores', $this->bia->scores(4320))
        ->assertSee('From the scores: 3 days.')
        ->set('rtoValue', '4')->set('rtoUnit', 'days')
        ->call('save')
        ->assertHasErrors(['rtoValue'])
        ->set('rtoValue', '2')
        ->set('overrideMtpd', true)->set('mtpdValue', '2')->set('mtpdUnit', 'days')
        ->call('save')
        ->assertHasErrors(['mtpdReason'])
        ->set('mtpdReason', 'Salaries are paid on the 2nd day')
        ->call('save')
        ->assertHasNoErrors();

    expect($assessment->fresh()->mtpd_minutes)->toBe(2880)
        ->and($assessment->fresh()->rto_minutes)->toBe(2880)
        ->and(AuditLog::query()->where('auditable_id', $assessment->id)->whereNotNull('new_values->impact_scores')->exists())->toBeTrue();

    $page->set('scores.'.array_key_first($this->bia->scores(1)), '')->call('submit')->assertHasErrors(['scores']);
});

it('refuses impact that goes down with time', function (): void {
    $this->actingAs($this->bia->owner);
    Livewire::test(ShowProcess::class, ['process' => $this->bia->payroll])->call('startAssessment');
    $scores = $this->bia->scores(1440);
    $category = ImpactCategory::query()->orderBy('sort_order')->first();
    $last = TimeHorizon::query()->orderByDesc('minutes')->first();
    $scores[$category->id.'|'.$last->id] = '1';

    Livewire::test(AssessmentPage::class, ['assessment' => Assessment::query()->sole()])
        ->set('scores', $scores)->set('rtoValue', '1')->set('rpoValue', '1')
        ->call('submit')
        ->assertHasErrors(['scores']);
});

it('sends a submitted assessment to the coordinator, who approves it into force', function (): void {
    $assessment = ownerSubmitsPayroll($this->bia);

    expect($assessment->status)->toBe(AssessmentStatus::Submitted);
    Notification::assertSentTo($this->bia->coordinator, AssessmentUpdate::class, fn (AssessmentUpdate $mail): bool => $mail->kind === 'submitted');
    Notification::assertNotSentTo($this->bia->owner, AssessmentUpdate::class);

    // The owner cannot change it any more, nor approve it.
    Livewire::test(AssessmentPage::class, ['assessment' => $assessment])->call('save')->assertForbidden();
    Livewire::test(AssessmentPage::class, ['assessment' => $assessment])->call('approve')->assertForbidden();

    $this->actingAs($this->bia->coordinator);
    Livewire::test(AssessmentPage::class, ['assessment' => $assessment])->call('approve')->assertHasNoErrors();

    $process = $this->bia->payroll->fresh();
    expect($assessment->fresh()->status)->toBe(AssessmentStatus::Approved)
        ->and($assessment->fresh()->unacceptable_level)->toBe(4)
        ->and($process->rto_minutes)->toBe(240)
        ->and($process->rpo_minutes)->toBe(1440)
        ->and($process->mtpd_minutes)->toBe(1440)
        ->and($process->tier)->toBe(Tier::One)
        ->and($process->next_review_on?->toDateString())->toBe(now()->addYear()->toDateString());
    Notification::assertSentTo([$this->bia->owner, $this->bia->deputy], AssessmentUpdate::class, fn (AssessmentUpdate $mail): bool => $mail->kind === 'approved');
});

it('returns an assessment only with a reason, and the owner fixes and resubmits it', function (): void {
    $assessment = ownerSubmitsPayroll($this->bia);
    $this->actingAs($this->bia->coordinator);

    Livewire::test(AssessmentPage::class, ['assessment' => $assessment])->call('sendBack')->assertHasErrors(['comment'])
        ->set('comment', 'RPO of a day is too long for payroll')->call('sendBack')->assertHasNoErrors();
    expect($assessment->fresh()->status)->toBe(AssessmentStatus::Returned);
    Notification::assertSentTo($this->bia->owner, AssessmentUpdate::class, fn (AssessmentUpdate $mail): bool => $mail->kind === 'returned' && $mail->comment === 'RPO of a day is too long for payroll');

    $this->actingAs($this->bia->deputy);
    Livewire::test(MyProcesses::class)->assertSee('Returned to you');
    Livewire::test(AssessmentPage::class, ['assessment' => $assessment])
        ->assertSee('RPO of a day is too long for payroll')
        ->set('rpoValue', '4')->set('rpoUnit', 'hours')->call('submit')->assertHasNoErrors();

    expect($assessment->fresh()->status)->toBe(AssessmentStatus::Submitted)->and($assessment->fresh()->submitted_by)->toBe($this->bia->deputy->id);
});

it('opens a review from the approved version and supersedes it on approval', function (): void {
    $first = ownerSubmitsPayroll($this->bia);
    $this->actingAs($this->bia->coordinator);
    Livewire::test(AssessmentPage::class, ['assessment' => $first])->call('approve');

    $this->actingAs($this->bia->owner);
    Livewire::test(ShowProcess::class, ['process' => $this->bia->payroll])->call('startAssessment');
    $second = Assessment::query()->where('version', 2)->sole();

    expect($second->rto_minutes)->toBe(240)
        ->and($second->workarounds)->toBe('Paper timesheets')
        ->and($second->scoreMap())->toBe($first->scoreMap());

    Livewire::test(AssessmentPage::class, ['assessment' => $second])->set('rtoValue', '1')->call('submit')->assertHasNoErrors();
    $this->actingAs($this->bia->coordinator);
    Livewire::test(AssessmentPage::class, ['assessment' => $second])->call('approve');

    expect($first->fresh()->status)->toBe(AssessmentStatus::Superseded)
        ->and($this->bia->payroll->fresh()->rto_minutes)->toBe(60);

    $this->actingAs($this->bia->owner);
    Livewire::test(AssessmentPage::class, ['assessment' => $first])->assertSee('This version can no longer change.')->call('save')->assertForbidden();
});

it('never lets an owner or the submitter approve, even as coordinator', function (): void {
    $this->bia->payroll->update(['owner_id' => $this->bia->coordinator->id]);
    $this->actingAs($this->bia->coordinator);
    Livewire::test(ShowProcess::class, ['process' => $this->bia->payroll])->call('startAssessment');
    $assessment = Assessment::query()->sole();

    Livewire::test(AssessmentPage::class, ['assessment' => $assessment])
        ->set('scores', $this->bia->scores(1440))->set('rtoValue', '4')->set('rpoValue', '1')
        ->call('submit')->assertHasNoErrors()
        ->call('approve')->assertForbidden();

    $second = $this->bia->member(DefaultRole::ContinuityCoordinator);
    $this->actingAs($second);
    Livewire::test(AssessmentPage::class, ['assessment' => $assessment])->call('approve')->assertHasNoErrors();
    expect($assessment->fresh()->status)->toBe(AssessmentStatus::Approved);
});

it('shows a process only to its owners and to people allowed to see the BIA', function (): void {
    $theirs = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => BusinessProcess::factory()->create());

    $this->actingAs($this->bia->employee)->get(route('bia.processes.show', $this->bia->payroll))->assertForbidden();
    $this->actingAs($this->bia->owner)->get(route('bia.processes.show', $this->bia->payroll))->assertOk();
    $this->actingAs($this->bia->owner)->get(route('home'))->assertSee('My processes');
    $this->actingAs($this->bia->employee)->get(route('home'))->assertDontSee('My processes');
    $this->actingAs($this->bia->coordinator)->get(route('bia.processes.show', $theirs))->assertNotFound();

    $assessment = ownerSubmitsPayroll($this->bia);
    $this->actingAs($this->bia->employee)->get(route('bia.assessments.show', $assessment))->assertForbidden();
    $this->actingAs($this->bia->lead)->get(route('bia.assessments.show', $assessment))->assertOk();
});
