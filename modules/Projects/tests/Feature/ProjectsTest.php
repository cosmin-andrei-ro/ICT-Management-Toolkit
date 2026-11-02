<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Livewire\Livewire;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Navigation\Navigation;
use Modules\Projects\Domain\Enums\PhaseKey;
use Modules\Projects\Domain\Enums\PhaseStatus;
use Modules\Projects\Domain\Enums\TaskStatus;
use Modules\Projects\Domain\Models\Project;
use Modules\Projects\Domain\Models\Task;
use Modules\Projects\Http\Livewire\MyTasks;
use Modules\Projects\Http\Livewire\ProjectForm;
use Modules\Projects\Http\Livewire\ProjectIndex;
use Modules\Projects\Http\Livewire\ShowProject;
use Modules\Projects\Tests\Support\ProjectsScenario;

beforeEach(function (): void {
    $this->projects = ProjectsScenario::create();
});

function startProject(ProjectsScenario $projects, string $name = 'Payroll replacement', string $kind = 'application'): Project
{
    test()->actingAs($projects->manager);
    Livewire::test(ProjectForm::class)
        ->set('name', $name)->set('kind', $kind)->set('managerId', $projects->manager->id)->set('sponsorId', $projects->sponsor->id)
        ->set('plannedStart', '2026-12-01')->set('plannedEnd', '2027-06-30')
        ->call('save')->assertHasNoErrors();

    return Project::query()->where('name', $name)->sole();
}

function addTask(Project $project, string $title, ?string $assigneeId = null, ?string $due = null): Task
{
    Livewire::test(ShowProject::class, ['project' => $project])
        ->set('taskTitle', $title)->set('taskPhaseId', $project->phases->first()->id)->set('taskAssigneeId', $assigneeId ?? '')->set('taskDueOn', $due ?? '')
        ->call('saveTask')->assertHasNoErrors();

    return Task::query()->where('title', $title)->sole();
}

it('starts a project with a code of its own and four phases, initiation under way', function (): void {
    $project = startProject($this->projects);
    $second = startProject($this->projects, 'Network refresh', 'infrastructure');

    expect($project->code)->toBe('PRJ-0001')->and($second->code)->toBe('PRJ-0002')
        ->and($project->phases->pluck('key')->all())->toBe(PhaseKey::cases())
        ->and($project->phases->first()->status)->toBe(PhaseStatus::Active)
        ->and($project->phases->where('status', PhaseStatus::Active))->toHaveCount(1)
        ->and(AuditLog::query()->where('auditable_id', $project->id)->where('event', 'created')->exists())->toBeTrue();

    $foreign = $this->projects->member(DefaultRole::Agent, Organization::factory()->create());
    Livewire::test(ProjectForm::class)->set('name', 'X')->set('managerId', $this->projects->manager->id)->set('sponsorId', $foreign->id)
        ->call('save')->assertHasErrors('sponsorId');
});

it('shows projects to staff who may read them and to the people involved, and to nobody else', function (): void {
    $project = startProject($this->projects);

    $auditor = $this->projects->member(DefaultRole::Auditor);
    $this->actingAs($auditor)->get(route('projects.show', $project))->assertOk();

    $this->actingAs($this->projects->outsider)->get(route('projects.index'))->assertForbidden();
    $this->actingAs($this->projects->outsider)->get(route('projects.show', $project))->assertForbidden();

    // The sponsor is a requester, yet sees the project they sponsor.
    $this->actingAs($this->projects->sponsor)->get(route('projects.show', $project))->assertOk();
    Livewire::test(ProjectIndex::class)->assertSee('Payroll replacement');
    $labels = fn ($user): array => collect(app(Navigation::class)->for($user))->flatten()->all();
    expect($labels($this->projects->sponsor))->toContain(__('projects::projects.menu'))
        ->and($labels($this->projects->outsider))->not->toContain(__('projects::projects.menu'));

    $other = Organization::factory()->create();
    $foreign = app(OrganizationContext::class)->runAs($other, fn () => Project::factory()->create());
    $this->actingAs($this->projects->manager)->get(route('projects.show', $foreign->id))->assertNotFound();
});

it('builds the team and gives tasks only to people on it', function (): void {
    $project = startProject($this->projects);
    $page = Livewire::test(ShowProject::class, ['project' => $project])
        ->set('memberId', $this->projects->engineer->id)->set('memberRole', 'Network engineer')->call('addMember')->assertHasNoErrors()
        ->set('memberId', $this->projects->engineer->id)->call('addMember')->assertHasErrors('memberId');

    $task = addTask($project->fresh(), 'Order servers', $this->projects->engineer->id, today()->subDay()->toDateString());
    expect($task->isOverdue())->toBeTrue();

    $page->set('taskTitle', 'Elsewhere')->set('taskPhaseId', $project->phases->first()->id)->set('taskAssigneeId', $this->projects->outsider->id)->call('saveTask')->assertHasErrors('taskAssigneeId');
    $otherProject = startProject($this->projects, 'Other');
    $page->set('taskTitle', 'Wrong phase')->set('taskPhaseId', $otherProject->phases->first()->id)->set('taskAssigneeId', '')->call('saveTask')->assertHasErrors('taskPhaseId');

    // Someone with open tasks stays on the team.
    $page->call('removeMember', $this->projects->engineer->id)->assertHasErrors('members');
});

it('moves tasks across the board: the manager any, a person their own', function (): void {
    $project = startProject($this->projects);
    Livewire::test(ShowProject::class, ['project' => $project])->set('memberId', $this->projects->engineer->id)->call('addMember');
    $mine = addTask($project->fresh(), 'Install servers', $this->projects->engineer->id);
    $theirs = addTask($project->fresh(), 'Write the runbook', $this->projects->manager->id);

    $this->actingAs($this->projects->engineer);
    Livewire::test(ShowProject::class, ['project' => $project])
        ->assertDontSee(__('projects::projects.add_task'))
        ->call('moveTask', $mine->id, 1)->call('moveTask', $mine->id, 1)->call('moveTask', $mine->id, 1);
    expect($mine->fresh()->status)->toBe(TaskStatus::Done)->and($mine->fresh()->completed_at)->not->toBeNull();

    Livewire::test(ShowProject::class, ['project' => $project])->call('moveTask', $theirs->id, 1)->assertForbidden();
    Livewire::test(ShowProject::class, ['project' => $project])->call('saveTask')->assertForbidden();

    $this->actingAs($this->projects->manager);
    Livewire::test(ShowProject::class, ['project' => $project])->call('moveTask', $mine->id, -1);
    expect($mine->fresh()->status)->toBe(TaskStatus::Blocked)->and($mine->fresh()->completed_at)->toBeNull();

    $this->actingAs($this->projects->engineer);
    Livewire::test(MyTasks::class)->assertSee('Install servers')->assertDontSee('Write the runbook')
        ->call('moveTask', $mine->id, -1);
    expect($mine->fresh()->status)->toBe(TaskStatus::InProgress);
});

it('plans phase dates and shows tasks as a board or a list', function (): void {
    $project = startProject($this->projects);
    $initiation = $project->phases->first();
    addTask($project, 'Business case');

    Livewire::test(ShowProject::class, ['project' => $project])
        ->call('editPhase', $initiation->id)->set('phaseStart', '2026-12-01')->set('phaseEnd', '2026-11-01')->call('savePhase')->assertHasErrors('phaseDates')
        ->set('phaseEnd', '2026-12-20')->call('savePhase')->assertHasNoErrors()
        ->assertSeeHtml('aria-labelledby="column-todo"')
        ->call('showAs', 'list')->assertSet('display', 'list')->assertSee('Business case')->assertDontSeeHtml('aria-labelledby="column-todo"');

    expect($initiation->fresh()->planned_end?->toDateString())->toBe('2026-12-20');
});

it('keeps closed projects as they ended', function (): void {
    $project = startProject($this->projects);
    $project->forceFill(['status' => 'closed', 'closed_at' => now()])->save();

    Livewire::test(ShowProject::class, ['project' => $project])->assertDontSee(__('projects::projects.add_task'))
        ->set('taskTitle', 'Late')->call('saveTask')->assertForbidden();
    $this->get(route('projects.edit', $project))->assertForbidden();
});
