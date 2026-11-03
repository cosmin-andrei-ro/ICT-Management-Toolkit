<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Projects\Application\Actions\RemindReports;
use Modules\Projects\Application\Actions\SaveProject;
use Modules\Projects\Application\Notifications\GateDecided;
use Modules\Projects\Application\Notifications\GateRequested;
use Modules\Projects\Application\Notifications\ReportsDue;
use Modules\Projects\Domain\Enums\PhaseKey;
use Modules\Projects\Domain\Enums\PhaseStatus;
use Modules\Projects\Domain\Enums\ProjectKind;
use Modules\Projects\Domain\Enums\ProjectStatus;
use Modules\Projects\Domain\Enums\Rag;
use Modules\Projects\Domain\Models\GateRequest;
use Modules\Projects\Domain\Models\Project;
use Modules\Projects\Domain\Models\StatusReport;
use Modules\Projects\Http\Livewire\MyTasks;
use Modules\Projects\Http\Livewire\Portfolio;
use Modules\Projects\Http\Livewire\ProjectGovernance;
use Modules\Projects\Tests\Support\ProjectsScenario;

beforeEach(function (): void {
    Notification::fake();
    $this->projects = ProjectsScenario::create();
    $this->actingAs($this->projects->manager);
    $this->project = app(SaveProject::class)->handle(null, [
        'name' => 'Payroll replacement', 'description' => null, 'kind' => ProjectKind::General,
        'sponsor_id' => $this->projects->sponsor->id, 'manager_id' => $this->projects->manager->id, 'planned_start' => null, 'planned_end' => null,
    ]);
});

function phaseStatuses(Project $project): array
{
    return $project->fresh()->phases->mapWithKeys(fn ($phase): array => [$phase->key->value => $phase->status->value])->all();
}

it('moves to the next phase only when the sponsor approves', function (): void {
    Livewire::test(ProjectGovernance::class, ['project' => $this->project])
        ->set('gateNote', 'Business case signed')->call('requestGate')->assertHasNoErrors()
        ->call('requestGate')->assertHasErrors('gate');
    Notification::assertSentTo($this->projects->sponsor, GateRequested::class);

    // The manager cannot decide.
    Livewire::test(ProjectGovernance::class, ['project' => $this->project])->call('approve')->assertForbidden();
    expect(phaseStatuses($this->project))->toMatchArray(['initiation' => 'active', 'planning' => 'pending']);

    $this->actingAs($this->projects->sponsor);
    Livewire::test(MyTasks::class)->assertSee('Payroll replacement');
    Livewire::test(ProjectGovernance::class, ['project' => $this->project])->assertSee('Business case signed')->call('approve')->assertHasNoErrors();

    expect(phaseStatuses($this->project))->toMatchArray(['initiation' => 'done', 'planning' => 'active'])
        ->and($this->project->fresh()->phases->firstWhere('key', PhaseKey::Initiation)->completed_at)->not->toBeNull();
    Notification::assertSentTo($this->projects->manager, GateDecided::class, fn (GateDecided $mail): bool => $mail->approved && $mail->to === 'Planning');
});

it('sends a request back only with a reason, and leaves the phase as it was', function (): void {
    Livewire::test(ProjectGovernance::class, ['project' => $this->project])->call('requestGate');

    $this->actingAs($this->projects->sponsor);
    Livewire::test(ProjectGovernance::class, ['project' => $this->project])
        ->call('reject')->assertHasErrors('decisionNote')
        ->set('decisionNote', 'The budget is not agreed yet')->call('reject')->assertHasNoErrors();

    expect(GateRequest::query()->sole()->status->value)->toBe('rejected')
        ->and(phaseStatuses($this->project))->toMatchArray(['initiation' => 'active']);
    Notification::assertSentTo($this->projects->manager, GateDecided::class, fn (GateDecided $mail): bool => ! $mail->approved && $mail->note === 'The budget is not agreed yet');

    // A new request can follow.
    $this->actingAs($this->projects->manager);
    Livewire::test(ProjectGovernance::class, ['project' => $this->project])->call('requestGate')->assertHasNoErrors();
});

it('closes the project when the sponsor approves the last gate', function (): void {
    foreach (range(1, 4) as $gate) {
        $this->actingAs($this->projects->manager);
        Livewire::test(ProjectGovernance::class, ['project' => $this->project])->call('requestGate')->assertHasNoErrors();
        $this->actingAs($this->projects->sponsor);
        Livewire::test(ProjectGovernance::class, ['project' => $this->project])->call('approve')->assertHasNoErrors();
    }

    $project = $this->project->fresh();
    expect($project->status)->toBe(ProjectStatus::Closed)->and($project->closed_at)->not->toBeNull()
        ->and($project->phases->every(fn ($phase): bool => $phase->status === PhaseStatus::Done))->toBeTrue();

    $this->actingAs($this->projects->manager);
    Livewire::test(ProjectGovernance::class, ['project' => $project])->assertDontSee(__('projects::governance.request_close'))->call('requestGate')->assertForbidden();
});

it('needs a sponsor other than the person asking', function (): void {
    $this->project->forceFill(['sponsor_id' => null])->save();
    Livewire::test(ProjectGovernance::class, ['project' => $this->project])->call('requestGate')->assertHasErrors('gate')
        ->assertSee(__('projects::governance.errors.no_sponsor'));

    $this->project->forceFill(['sponsor_id' => $this->projects->manager->id])->save();
    Livewire::test(ProjectGovernance::class, ['project' => $this->project])->call('requestGate')->assertHasErrors('gate');
    expect(GateRequest::query()->exists())->toBeFalse();
});

it('keeps status reports, one per day, and reminds managers when one is due', function (): void {
    Livewire::test(ProjectGovernance::class, ['project' => $this->project])
        ->call('startReport')->set('overall', 'amber')->set('schedule', 'red')->call('saveReport')->assertHasErrors(['achievements', 'nextSteps'])
        ->set('achievements', 'Vendor shortlist')->set('nextSteps', 'Demos')->call('saveReport')->assertHasNoErrors()
        ->call('startReport')->assertSet('overall', 'amber')->set('achievements', 'Vendor shortlist and demos booked')->set('nextSteps', 'Demos')->call('saveReport');

    $report = StatusReport::query()->sole();
    expect($report->overall)->toBe(Rag::Amber)->and($report->schedule)->toBe(Rag::Red)->and($report->achievements)->toBe('Vendor shortlist and demos booked');

    $this->actingAs($this->projects->engineer);
    Livewire::test(ProjectGovernance::class, ['project' => $this->project])->assertForbidden();

    // Fifteen days later the report is due; a project reported last week is not.
    $later = CarbonImmutable::today()->addDays(15);
    $recent = app(SaveProject::class)->handle(null, ['name' => 'Recent', 'description' => null, 'kind' => ProjectKind::General, 'sponsor_id' => null, 'manager_id' => $this->projects->manager->id, 'planned_start' => null, 'planned_end' => null]);
    StatusReport::query()->forceCreate(['project_id' => $recent->id, 'reported_on' => $later->subDays(3)->toDateString(), 'overall' => 'green', 'schedule' => 'green', 'budget' => 'green', 'scope' => 'green', 'achievements' => 'x', 'next_steps' => 'y']);
    $this->travelTo($later);
    expect(app(RemindReports::class)->handle($later))->toBe(1);
    Notification::assertSentTo($this->projects->manager, ReportsDue::class, fn (ReportsDue $mail): bool => count($mail->projects) === 1 && str_contains($mail->projects[0]['name'], 'Payroll replacement'));
});

it('shows management the portfolio with each project\'s latest status', function (): void {
    StatusReport::query()->forceCreate(['project_id' => $this->project->id, 'reported_on' => today()->toDateString(), 'overall' => 'red', 'schedule' => 'red', 'budget' => 'green', 'scope' => 'amber', 'achievements' => 'x', 'next_steps' => 'y']);
    Livewire::test(ProjectGovernance::class, ['project' => $this->project])->call('requestGate');

    $auditor = $this->projects->member(DefaultRole::Auditor);
    $this->actingAs($auditor);
    Livewire::test(Portfolio::class)->assertSee('PRJ-0001')->assertSee(__('projects::governance.gate_waiting'))->assertSee('Red');

    $this->actingAs($this->projects->sponsor)->get(route('projects.portfolio'))->assertForbidden();
});
