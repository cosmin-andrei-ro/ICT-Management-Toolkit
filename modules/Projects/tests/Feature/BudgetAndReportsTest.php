<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Metrics\Metrics;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Projects\Application\Actions\ManageTasks;
use Modules\Projects\Application\Actions\SaveProject;
use Modules\Projects\Application\Actions\StageGates;
use Modules\Projects\Application\BudgetFigures;
use Modules\Projects\Application\Exports\PortfolioExport;
use Modules\Projects\Domain\Enums\ProjectKind;
use Modules\Projects\Domain\Models\StatusReport;
use Modules\Projects\Http\Livewire\Portfolio;
use Modules\Projects\Http\Livewire\ProjectBudget;
use Modules\Projects\Tests\Support\ProjectsScenario;

beforeEach(function (): void {
    Notification::fake();
    $this->projects = ProjectsScenario::create();
    $this->actingAs($this->projects->manager);
    $this->project = app(SaveProject::class)->handle(null, [
        'name' => 'Network refresh', 'description' => null, 'kind' => ProjectKind::Infrastructure,
        'sponsor_id' => $this->projects->sponsor->id, 'manager_id' => $this->projects->manager->id, 'planned_start' => null, 'planned_end' => null,
    ]);
    $this->supplier = Supplier::factory()->create(['name' => 'Demo Networks']);
});

function addCost(object $test, string $description, string $amount, string $category = 'hardware', ?string $supplierId = null): void
{
    Livewire::test(ProjectBudget::class, ['project' => $test->project])
        ->set('costDescription', $description)->set('costAmount', $amount)->set('costCategory', $category)->set('costSupplierId', $supplierId ?? '')->set('costInvoice', 'INV-1')
        ->call('addCost')->assertHasNoErrors();
}

it('records the budget and the costs against it, by category', function (): void {
    Livewire::test(ProjectBudget::class, ['project' => $this->project])
        ->call('editBudget')->assertSet('currency', 'EUR')->set('budgetAmount', '50000')->call('saveBudget')->assertHasNoErrors();
    addCost($this, 'Core switches', '42000', 'hardware', $this->supplier->id);
    addCost($this, 'Installation', '9500', 'external_labour');
    addCost($this, 'Credit note', '-500', 'hardware');

    $figures = BudgetFigures::of($this->project->fresh());
    expect($figures->budget)->toBe(50000.0)->and($figures->spent)->toBe(51000.0)->and($figures->remaining)->toBe(-1000.0)
        ->and($figures->percent)->toBe(102)->and($figures->isOver())->toBeTrue()
        ->and($figures->byCategory['hardware'])->toBe(41500.0)->and($figures->byCategory['external_labour'])->toBe(9500.0);

    Livewire::test(ProjectBudget::class, ['project' => $this->project])->assertSee(__('projects::budget.over'))->assertSee('Demo Networks')
        ->set('costDescription', 'Zero')->set('costAmount', '0')->call('addCost')->assertHasErrors('costAmount')
        ->call('editBudget')->set('currency', 'USD')->call('saveBudget')->assertHasErrors('currency');
});

it('keeps the budget to the project manager and suppliers to the organization', function (): void {
    $foreign = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => Supplier::factory()->create());
    Livewire::test(ProjectBudget::class, ['project' => $this->project])
        ->set('costDescription', 'X')->set('costAmount', '10')->set('costSupplierId', $foreign->id)->call('addCost')->assertHasErrors('costSupplierId');

    $this->actingAs($this->projects->sponsor);
    Livewire::test(ProjectBudget::class, ['project' => $this->project])->assertDontSee(__('projects::budget.add_cost'))
        ->set('costDescription', 'X')->set('costAmount', '10')->call('addCost')->assertForbidden();
    $this->actingAs($this->projects->outsider)->get(route('projects.report', $this->project))->assertForbidden();
});

it('measures the projects for the dashboards and exports the portfolio', function (): void {
    $this->project->forceFill(['budget_amount' => '1000', 'currency' => 'EUR'])->save();
    addCost($this, 'Switch', '1500');
    StatusReport::query()->forceCreate(['project_id' => $this->project->id, 'reported_on' => today()->toDateString(), 'overall' => 'red', 'schedule' => 'red', 'budget' => 'red', 'scope' => 'green', 'achievements' => 'Ordered', 'next_steps' => 'Install', 'issues' => 'Late delivery']);
    app(ManageTasks::class)->save($this->project, null, ['phase_id' => $this->project->phases->first()->id, 'title' => 'Survey the rooms', 'description' => null, 'assignee_id' => null, 'due_on' => today()->subDays(2)->toDateString()]);
    app(StageGates::class)->request($this->project, $this->projects->manager, null);

    $values = app(Metrics::class)->all();
    expect($values)->toMatchArray(['projects.red' => 1, 'projects.over_budget' => 1, 'projects.overdue_tasks' => 1, 'projects.gates_waiting' => 1]);

    $auditor = $this->projects->member(DefaultRole::Auditor);
    $this->actingAs($auditor);
    Livewire::test(Portfolio::class)->call('export', 'xlsx');
    expect(Export::query()->where('type', PortfolioExport::TYPE)->exists())->toBeTrue();

    $export = Export::factory()->create(['type' => PortfolioExport::TYPE, 'user_id' => $auditor->id]);
    [$projects, $costs, , $reports] = app(PortfolioExport::class)->sheets($export);
    $rows = fn ($sheet): array => array_map(fn (array $values): array => array_combine($sheet->headings, $values), iterator_to_array($sheet->rows, false));

    expect($rows($projects)[0])->toMatchArray(['Code' => 'PRJ-0001', 'Latest status' => 'Red', 'Approved budget' => 1000.0, 'Spent' => 1500.0, 'Tasks overdue' => 1])
        ->and($rows($costs)[0])->toMatchArray(['Code' => 'PRJ-0001', 'Amount' => 1500.0, 'Currency' => 'EUR'])
        ->and($rows($reports)[0])->toMatchArray(['Overall' => 'Red', 'Issues and risks' => 'Late delivery']);
});

it('prints the status report, and falls back without the PDF service', function (): void {
    StatusReport::query()->forceCreate(['project_id' => $this->project->id, 'reported_on' => today()->toDateString(), 'overall' => 'amber', 'schedule' => 'green', 'budget' => 'amber', 'scope' => 'green', 'achievements' => 'Vendors shortlisted', 'next_steps' => 'Order', 'issues' => null]);
    app(ManageTasks::class)->save($this->project, null, ['phase_id' => $this->project->phases->first()->id, 'title' => 'Survey the rooms', 'description' => null, 'assignee_id' => null, 'due_on' => today()->subDay()->toDateString()]);

    $this->get(route('projects.report', $this->project))->assertOk()
        ->assertSee('PRJ-0001 · Network refresh')->assertSee('Vendors shortlisted')->assertSee('Survey the rooms')->assertSee(__('projects::budget.heading'));

    config(['core.pdf.gotenberg_url' => '']);
    $this->get(route('projects.report.pdf', $this->project))->assertRedirect(route('projects.report', $this->project));
});
