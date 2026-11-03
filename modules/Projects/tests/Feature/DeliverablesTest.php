<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Core\Domain\Links\Applications;
use Modules\Core\Domain\Links\ApplicationSummary;
use Modules\Core\Domain\Links\BusinessProcesses;
use Modules\Core\Domain\Links\LinkedRecord;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Links\ProcessSummary;
use Modules\Core\Domain\Links\Risks;
use Modules\Core\Domain\Models\User;
use Modules\Projects\Application\Actions\SaveProject;
use Modules\Projects\Application\ClosureReadiness;
use Modules\Projects\Domain\Enums\ProjectKind;
use Modules\Projects\Domain\Enums\ProjectStatus;
use Modules\Projects\Domain\Models\Deliverable;
use Modules\Projects\Domain\Models\Project;
use Modules\Projects\Http\Livewire\ProjectDeliverables;
use Modules\Projects\Http\Livewire\ProjectGovernance;
use Modules\Projects\Tests\Support\ProjectsScenario;

/**
 * The portfolio, the risk register and the BIA as the checklist sees them,
 * set by each test.
 */
final class ChecklistWorld implements Applications, BusinessProcesses, Risks
{
    public bool $documented = false;

    public ?string $item = null;

    public int $risks = 0;

    /** @var array<string, ?int> process id => RTO */
    public array $processes = [];

    public function isAvailable(): bool
    {
        return true;
    }

    public function search(string $terms, int $limit = 10): array
    {
        return [$this->app()];
    }

    public function find(array $ids): array
    {
        $found = [];

        foreach ($ids as $id) {
            if ($id === '0199a000-0000-7000-8000-000000000001') {
                $found[$id] = $this->app();
            } elseif (array_key_exists($id, $this->processes)) {
                $found[$id] = new ProcessSummary($id, 'Payroll', 'https://example.test/p', $this->processes[$id], null, 1);
            }
        }

        return $found;
    }

    public function concerning(string $assetType, string $assetId, User $viewer): array
    {
        return [];
    }

    public function assessedCount(string $assetType, string $assetId): int
    {
        return $assetType === 'application' && $assetId === '0199a000-0000-7000-8000-000000000001' ? $this->risks : 0;
    }

    public function assessed(): array
    {
        return [];
    }

    private function app(): ApplicationSummary
    {
        return new ApplicationSummary('0199a000-0000-7000-8000-000000000001', 'APP-007', 'Payroll', 'https://example.test/app', null, $this->documented, $this->item, array_keys($this->processes));
    }
}

beforeEach(function (): void {
    Notification::fake();
    $this->projects = ProjectsScenario::create();
    $this->world = new ChecklistWorld;
    foreach ([Applications::class, Risks::class, BusinessProcesses::class] as $contract) {
        app()->instance($contract, $this->world);
    }
    $this->actingAs($this->projects->manager);
    $this->project = app(SaveProject::class)->handle(null, [
        'name' => 'Payroll replacement', 'description' => null, 'kind' => ProjectKind::Application,
        'sponsor_id' => $this->projects->sponsor->id, 'manager_id' => $this->projects->manager->id, 'planned_start' => null, 'planned_end' => null,
    ]);
});

function walkToClosure(Project $project, ProjectsScenario $projects): void
{
    foreach (range(1, 3) as $gate) {
        test()->actingAs($projects->manager);
        Livewire::test(ProjectGovernance::class, ['project' => $project])->call('requestGate')->assertHasNoErrors();
        test()->actingAs($projects->sponsor);
        Livewire::test(ProjectGovernance::class, ['project' => $project])->call('approve')->assertHasNoErrors();
    }
    test()->actingAs($projects->manager);
}

it('reads the new-application checklist from the other modules', function (): void {
    $readiness = app(ClosureReadiness::class);
    expect($readiness->checklist($this->project))->toBe(['application' => false, 'documentation' => false, 'cmdb' => false, 'risks' => false, 'bia' => false]);

    Livewire::test(ProjectDeliverables::class, ['project' => $this->project])->set('applicationTerm', 'pay')->assertSee('APP-007')->call('chooseApplication', '0199a000-0000-7000-8000-000000000001')->assertHasNoErrors();
    $project = $this->project->fresh();
    expect($readiness->checklist($project))->toBe(['application' => true, 'documentation' => false, 'cmdb' => false, 'risks' => false, 'bia' => false]);

    $this->world->documented = true;
    $this->world->item = '0199a000-0000-7000-8000-000000000003';
    $this->world->risks = 2;
    $this->world->processes = ['0199a000-0000-7000-8000-000000000004' => 240, '0199a000-0000-7000-8000-000000000005' => null];
    expect($readiness->checklist($project)['bia'])->toBeFalse();
    $this->world->processes['0199a000-0000-7000-8000-000000000005'] = 60;
    expect($readiness->checklist($project))->toBe(array_fill_keys(ClosureReadiness::CHECKS, true));

    // Other projects have no checklist; an unknown application is refused.
    $general = app(SaveProject::class)->handle(null, ['name' => 'Other', 'description' => null, 'kind' => ProjectKind::General, 'sponsor_id' => null, 'manager_id' => $this->projects->manager->id, 'planned_start' => null, 'planned_end' => null]);
    expect($readiness->checklist($general))->toBe([]);
    Livewire::test(ProjectDeliverables::class, ['project' => $project])->call('chooseApplication', '0199a000-0000-7000-8000-000000000006')->assertHasErrors('applicationId');
    Livewire::test(ProjectDeliverables::class, ['project' => $general])->call('chooseApplication', '0199a000-0000-7000-8000-000000000001')->assertHasErrors('applicationId');
});

it('keeps deliverables per phase, linked to records the person may see', function (): void {
    app(LinkedRecords::class)->register('test.change', fn (array $ids, User $viewer): array => in_array('0199a000-0000-7000-8000-000000000002', $ids, true) ? [new LinkedRecord('0199a000-0000-7000-8000-000000000002', 'CHG-000042 Go-live', 'https://example.test/chg', 'Approved')] : []);
    app(LinkedRecords::class)->registerSearch('test.change', 'projects::deliverables.kind', fn (string $term, User $viewer): array => [new LinkedRecord('0199a000-0000-7000-8000-000000000002', 'CHG-000042 Go-live', 'https://example.test/chg', 'Approved')]);

    $page = Livewire::test(ProjectDeliverables::class, ['project' => $this->project])
        ->set('deliverableTitle', 'Go-live change')->set('deliverablePhaseId', $this->project->phases->firstWhere('key.value', 'execution')->id)->call('add')->assertHasNoErrors()
        ->set('deliverableTitle', 'Nice to have')->set('deliverablePhaseId', $this->project->phases->first()->id)->set('deliverableRequired', false)->call('add')->assertHasNoErrors();
    $change = Deliverable::query()->where('title', 'Go-live change')->sole();

    $page->call('startLinking', $change->id)->set('linkType', 'test.change')->set('linkTerm', 'go')->assertSee('CHG-000042 Go-live')
        ->call('link', '0199a000-0000-7000-8000-000000000002')->assertHasNoErrors()
        ->call('deliver', $change->id)
        ->assertSeeHtml('href="https://example.test/chg"');
    expect($change->fresh())->linked_type->toBe('test.change')->linked_title->toBe('CHG-000042 Go-live')->delivered_at->not->toBeNull();

    $page->call('startLinking', Deliverable::query()->where('title', 'Nice to have')->sole()->id)->set('linkType', 'test.change')->call('link', '0199a000-0000-7000-8000-000000000007')->assertHasErrors('linkTerm');

    // Another person on the project cannot change them.
    $this->actingAs($this->projects->sponsor);
    Livewire::test(ProjectDeliverables::class, ['project' => $this->project])->call('deliver', $change->id)->assertForbidden();
});

it('closes only once the checklist and the required deliverables are done', function (): void {
    Livewire::test(ProjectDeliverables::class, ['project' => $this->project])
        ->set('deliverableTitle', 'Training delivered')->set('deliverablePhaseId', $this->project->phases->first()->id)->call('add');
    walkToClosure($this->project, $this->projects);

    Livewire::test(ProjectGovernance::class, ['project' => $this->project])->call('requestGate')->assertHasErrors('gate')
        ->assertSee(__('projects::deliverables.checks.application'))->assertSee('Training delivered');

    $this->project->forceFill(['application_id' => '0199a000-0000-7000-8000-000000000001'])->save();
    $this->world->documented = true;
    $this->world->item = '0199a000-0000-7000-8000-000000000003';
    $this->world->risks = 1;
    $this->world->processes = ['0199a000-0000-7000-8000-000000000004' => 240];
    Deliverable::query()->update(['delivered_at' => now()]);
    Livewire::test(ProjectGovernance::class, ['project' => $this->project])->call('requestGate')->assertHasNoErrors();

    // The documentation was withdrawn meanwhile: the sponsor cannot close yet.
    $this->world->documented = false;
    $this->actingAs($this->projects->sponsor);
    Livewire::test(ProjectGovernance::class, ['project' => $this->project])->call('approve')->assertHasErrors('gate');
    expect($this->project->fresh()->status)->toBe(ProjectStatus::Active);

    $this->world->documented = true;
    Livewire::test(ProjectGovernance::class, ['project' => $this->project])->call('approve')->assertHasNoErrors();
    expect($this->project->fresh()->status)->toBe(ProjectStatus::Closed);
});
