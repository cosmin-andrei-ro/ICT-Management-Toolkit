<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Bcm\Domain\Enums\ExerciseStatus;
use Modules\Bcm\Domain\Models\Exercise;
use Modules\Bcm\Domain\Models\ExerciseItem;
use Modules\Bcm\Domain\Models\ExerciseStep;
use Modules\Bcm\Domain\Models\Finding;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bcm\Http\Livewire\ExerciseForm;
use Modules\Bcm\Http\Livewire\ExercisePage;
use Modules\Bcm\Http\Livewire\ShowPlan;
use Modules\Bcm\Tests\Support\BcmScenario;
use Modules\Cmdb\Domain\Models\CiLink;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Cmdb\Http\Livewire\ShowItem;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Models\Ticket;

beforeEach(function (): void {
    Notification::fake();
    $this->bcm = BcmScenario::create();
});

function plannedExercise(Plan $plan): Exercise
{
    Livewire::test(ExerciseForm::class, ['plan' => $plan])
        ->set('type', 'technical')
        ->set('title', 'Restore payroll in the recovery site')
        ->set('scheduledOn', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    return Exercise::query()->where('plan_id', $plan->id)->sole();
}

it('exercises only plans in force, by those who keep them up to date', function (): void {
    $plan = Plan::factory()->create(['owner_id' => $this->bcm->owner->id]);
    $this->actingAs($this->bcm->owner);
    Livewire::test(ExerciseForm::class, ['plan' => $plan])->assertForbidden();

    $plan = $this->bcm->approvedPayrollDrp();

    $this->actingAs($this->bcm->agent);
    Livewire::test(ShowPlan::class, ['plan' => $plan])->assertDontSee('Plan an exercise');
    Livewire::test(ExerciseForm::class, ['plan' => $plan])->assertForbidden();

    $this->actingAs($this->bcm->employee);
    Livewire::test(ExerciseForm::class, ['plan' => $plan])->assertForbidden();

    $this->actingAs($this->bcm->owner);
    $exercise = plannedExercise($plan);
    expect($exercise->status)->toBe(ExerciseStatus::Planned);

    // Agents follow the exercises but do not record them.
    $this->actingAs($this->bcm->agent);
    Livewire::test(ExercisePage::class, ['exercise' => $exercise])->assertSee('Restore payroll in the recovery site')->call('start')->assertForbidden();
    $this->actingAs($this->bcm->employee);
    $this->get(route('bcm.exercises.show', $exercise))->assertForbidden();
});

it('copies the runbook in force, records results and confirms the recovery in the CMDB', function (): void {
    $plan = $this->bcm->approvedPayrollDrp();
    $this->actingAs($this->bcm->lead);
    $exercise = plannedExercise($plan);

    $page = Livewire::test(ExercisePage::class, ['exercise' => $exercise])->call('start')->assertHasNoErrors();
    $steps = ExerciseStep::query()->where('exercise_id', $exercise->id)->orderBy('position')->get();
    $item = ExerciseItem::query()->where('exercise_id', $exercise->id)->sole();
    expect($steps->pluck('action')->all())->toBe(['Declare the incident', 'Restore the database from backup'])
        ->and($item->configuration_item_id)->toBe($this->bcm->payrollApp->id);

    // Completion needs a result for every step.
    $page->set('stepResults.'.$steps[0]->id.'.result', 'passed')->set('stepResults.'.$steps[0]->id.'.value', '30')->set('stepResults.'.$steps[0]->id.'.unit', 'minutes')
        ->set('outcome', 'partial')
        ->call('complete')
        ->assertHasErrors('steps');

    $page->set('stepResults.'.$steps[1]->id.'.result', 'issues')->set('stepResults.'.$steps[1]->id.'.value', '5')->set('stepResults.'.$steps[1]->id.'.unit', 'hours')
        ->set('itemResults.'.$item->id.'.recovered', 'yes')->set('itemResults.'.$item->id.'.value', '5')->set('itemResults.'.$item->id.'.unit', 'hours')
        ->call('saveResults')
        ->assertHasNoErrors()
        // 30 minutes + 5 hours against the 4-hour RTO of payroll.
        ->assertSee('Activation and recovery took 330 minutes')
        ->assertSee('Longer than the strictest RTO (4 hours)')
        ->set('lessons', 'Backups restore slower than expected')
        ->call('complete')
        ->assertHasNoErrors()
        ->assertSee('Longer than the strictest RTO');

    $exercise->refresh();
    expect($exercise->status)->toBe(ExerciseStatus::Completed)
        ->and($exercise->measured_minutes)->toBe(330)
        ->and($exercise->rto_minutes)->toBe(240)
        ->and($plan->fresh()->last_exercised_on?->toDateString())->toBe(now()->toDateString());

    $app = ConfigurationItem::query()->findOrFail($this->bcm->payrollApp->id);
    expect($app->recovery_verified_on?->toDateString())->toBe(now()->toDateString())
        ->and($app->recovery_verified_minutes)->toBe(300)
        ->and($app->recovery_verified_by)->toContain('Payroll DRP');
    Livewire::test(ShowItem::class, ['item' => $app])->assertSee('Recovery last tested')->assertSee('5 hours');

    // Kept as evidence: no further change.
    Livewire::test(ExercisePage::class, ['exercise' => $exercise])->assertSee('Print the report')->call('saveResults')->assertForbidden();
    expect($exercise->auditLogs()->count())->toBeGreaterThan(1);
});

it('raises findings as problems in the service desk, once, with the item of the step', function (): void {
    $plan = $this->bcm->approvedPayrollDrp();
    $this->actingAs($this->bcm->owner);
    $exercise = plannedExercise($plan);
    $page = Livewire::test(ExercisePage::class, ['exercise' => $exercise])->call('start');
    $step = ExerciseStep::query()->where('exercise_id', $exercise->id)->where('position', 2)->sole();

    $page->set('findingDescription', 'The backup of last night was missing')->set('findingSeverity', 'high')->set('findingStepId', $step->id)->call('addFinding')->assertHasNoErrors();
    $finding = Finding::query()->where('exercise_id', $exercise->id)->sole();

    $page->call('raiseTicket', $finding->id)->assertHasNoErrors();
    $problem = Ticket::query()->where('type', TicketType::Problem)->sole();
    expect($problem->title)->toContain('Payroll DRP')->toContain('backup of last night')
        ->and($problem->impact->value)->toBe('high')
        ->and($finding->fresh()->ticket_number)->toBe($problem->number)
        ->and(CiLink::query()->where('linkable_type', 'ticketing.ticket')->where('linkable_id', $problem->id)->pluck('configuration_item_id')->all())->toBe([$this->bcm->payrollApp->id]);

    $page->assertSee($problem->number)->call('raiseTicket', $finding->id)->assertHasErrors('findings')->call('removeFinding', $finding->id)->assertHasErrors('findings');
    expect(Ticket::query()->where('type', TicketType::Problem)->count())->toBe(1);
});

it('cancels an exercise and hides exercises of other organizations', function (): void {
    $plan = $this->bcm->approvedPayrollDrp();
    $this->actingAs($this->bcm->owner);
    $exercise = plannedExercise($plan);
    Livewire::test(ExercisePage::class, ['exercise' => $exercise])->call('cancelExercise')->assertHasNoErrors();
    expect($exercise->fresh()->status)->toBe(ExerciseStatus::Cancelled);
    Livewire::test(ExercisePage::class, ['exercise' => $exercise])->call('start')->assertForbidden();

    app(OrganizationContext::class)->set(Organization::factory()->create());
    $other = Exercise::factory()->create();
    app(OrganizationContext::class)->set($this->bcm->organization);
    $this->actingAs($this->bcm->coordinator)->get(route('bcm.exercises.show', $other->id))->assertNotFound();
});
