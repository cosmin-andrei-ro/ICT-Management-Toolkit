<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Modules\Core\Database\Seeders\DemoClock;
use Modules\Core\Database\Seeders\DemoCompany;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Projects\Application\Actions\ManageBudget;
use Modules\Projects\Application\Actions\ManageDeliverables;
use Modules\Projects\Application\Actions\ManageTasks;
use Modules\Projects\Application\Actions\ManageTeam;
use Modules\Projects\Application\Actions\SaveProject;
use Modules\Projects\Application\Actions\StageGates;
use Modules\Projects\Application\Actions\WriteStatusReport;
use Modules\Projects\Domain\Enums\CostCategory;
use Modules\Projects\Domain\Enums\PhaseKey;
use Modules\Projects\Domain\Enums\ProjectKind;
use Modules\Projects\Domain\Enums\Rag;
use Modules\Projects\Domain\Enums\TaskStatus;
use Modules\Projects\Domain\Models\Deliverable;
use Modules\Projects\Domain\Models\Project;

/**
 * Four IT projects at different stages, run by the project manager: the CRM
 * rollout (an application project whose closure checklist is still
 * incomplete), the core switch replacement (over budget), the second
 * storage array (waiting for the sponsor at the planning gate) and the
 * awareness programme, closed. Weekly RAG reports, tasks on the board,
 * deliverables linked to the records they produced.
 */
final class ProjectsDemoSeeder extends Seeder
{
    public function __construct(
        private SaveProject $projects,
        private ManageTeam $team,
        private ManageTasks $tasks,
        private StageGates $gates,
        private WriteStatusReport $reports,
        private ManageBudget $budget,
        private ManageDeliverables $deliverables,
    ) {}

    public function run(DemoCompany $company, OrganizationContext $context): void
    {
        $context->runAs($company->organization, function () use ($company): void {
            // Dates are taken before the clock moves: reading today resets it.
            $today = DemoClock::today();

            $this->crm($company, $today);
            $this->coreSwitch($company, $today);
            $this->storage($company, $today);
            $this->awareness($company, $today);
        });

        DemoClock::reset();
    }

    private function crm(DemoCompany $company, CarbonImmutable $today): void
    {
        $project = $this->start($company, $today, 80, 'CRM rollout', 'Replace the sales spreadsheets with the CRM, integrated with the ERP.', ProjectKind::Application, 'employee_sales', 120, ['applications' => 'Application manager', 'agent' => 'Technical lead', 'employee_sales' => 'Key user']);
        $this->deliverables->chooseApplication($project, $company->ref('applications.crm'));
        $this->budget->setBudget($project, '60000.00', 'EUR');

        $this->advance($company, $project, $today, 72, 'Business case and scope agreed.');
        $this->advance($company, $project, $today, 50, 'Supplier chosen, plan and data migration approach agreed.');

        $this->task($company, $project, $today, PhaseKey::Planning, 'Choose the CRM supplier', 'employee_sales', 55, TaskStatus::Done);
        $this->task($company, $project, $today, PhaseKey::Execution, 'Configure sales pipelines', 'agent', 20, TaskStatus::Done);
        $this->task($company, $project, $today, PhaseKey::Execution, 'ERP customer integration', 'agent', -3, TaskStatus::InProgress);
        $this->task($company, $project, $today, PhaseKey::Execution, 'Migrate customer accounts', 'applications', 10, TaskStatus::Todo);
        $this->task($company, $project, $today, PhaseKey::Execution, 'Train the sales team', 'employee_sales', 30, TaskStatus::Todo);
        $this->task($company, $project, $today, PhaseKey::Execution, 'Approve the CRM documentation sheet', 'applications', 5, TaskStatus::Blocked);

        $sheet = $this->deliverable($project, PhaseKey::Execution, 'Application documentation approved', true);
        $this->deliverables->link($sheet, 'applications.application', $company->ref('applications.crm'), $company->user('applications'));
        $this->deliverable($project, PhaseKey::Execution, 'Integration test report', true);
        $this->deliverable($project, PhaseKey::Closure, 'Handover to the service desk', true);

        $this->cost($company, $project, $today, 45, CostCategory::Licences, 'CRM subscription, first year', '18000.00', 'cloud');
        $this->cost($company, $project, $today, 30, CostCategory::ExternalLabour, 'Implementation partner, first milestone', '16500.00', null);
        $this->cost($company, $project, $today, 8, CostCategory::ExternalLabour, 'Implementation partner, integration', '6500.00', null);

        $this->reports($company, $project, $today, [
            [42, Rag::Green, Rag::Green, Rag::Green, 'Pipelines configured.', 'Start the ERP integration.', null],
            [28, Rag::Green, Rag::Amber, Rag::Green, 'Integration designed.', 'Build and test the integration.', 'The ERP supplier answers slowly.'],
            [14, Rag::Amber, Rag::Amber, Rag::Green, 'First integration build.', 'Fix the customer number mapping.', 'Integration two weeks late.'],
            [2, Rag::Amber, Rag::Amber, Rag::Amber, 'Customer mapping fixed.', 'Migrate the accounts.', 'Documentation sheet still waiting for security approval.'],
        ]);
    }

    private function coreSwitch(DemoCompany $company, CarbonImmutable $today): void
    {
        $project = $this->start($company, $today, 75, 'Core switch replacement', 'Replace the out-of-support core switch in Bucharest.', ProjectKind::Infrastructure, 'teamlead', 40, ['agent2' => 'Network engineer', 'assets' => 'Asset manager']);
        $this->budget->setBudget($project, '18000.00', 'EUR');

        $this->advance($company, $project, $today, 70, 'Replacement agreed after the risk review.');
        $this->advance($company, $project, $today, 45, 'Design and maintenance window agreed.');

        $this->task($company, $project, $today, PhaseKey::Planning, 'Order the new switches', 'assets', 50, TaskStatus::Done);
        $this->task($company, $project, $today, PhaseKey::Execution, 'Pre-configure the new switches', 'agent2', 15, TaskStatus::Done);
        $this->task($company, $project, $today, PhaseKey::Execution, 'Migrate the server ports', 'agent2', 4, TaskStatus::InProgress);
        $this->task($company, $project, $today, PhaseKey::Execution, 'Retire the old switch in ITAM', 'assets', 14, TaskStatus::Todo);

        $risk = $this->deliverable($project, PhaseKey::Initiation, 'Risk treated', false);
        $this->deliverables->link($risk, 'risk.risk', $company->ref('risk.core_switch'), $company->user('risk'));
        $runbook = $this->deliverable($project, PhaseKey::Execution, 'Patching procedure updated', true);
        $this->deliverables->link($runbook, 'documentation.page', $company->ref('documentation.patching'), $company->user('teamlead'));

        $this->cost($company, $project, $today, 48, CostCategory::Hardware, 'Two core switches', '14800.00', 'network');
        $this->cost($company, $project, $today, 20, CostCategory::Services, 'Weekend installation support', '4200.00', 'network');
        $this->cost($company, $project, $today, 6, CostCategory::Hardware, 'Additional SFP+ modules', '2400.00', 'network');

        $this->reports($company, $project, $today, [
            [35, Rag::Green, Rag::Green, Rag::Green, 'Switches delivered.', 'Pre-configuration.', null],
            [21, Rag::Amber, Rag::Green, Rag::Amber, 'Pre-configuration done.', 'Migrate the server ports.', 'Installation support costs more than planned.'],
            [7, Rag::Red, Rag::Amber, Rag::Red, 'Half of the ports migrated.', 'Finish the migration in the next window.', 'Over budget: extra SFP+ modules were needed.'],
        ]);
    }

    private function storage(DemoCompany $company, CarbonImmutable $today): void
    {
        $project = $this->start($company, $today, 30, 'Second storage array', 'Add a second storage array in rack BUC-R03 for replicated backups.', ProjectKind::Infrastructure, 'employee_finance', 150, ['teamlead' => 'Infrastructure lead']);
        $this->budget->setBudget($project, '45000.00', 'EUR');
        $this->advance($company, $project, $today, 25, 'Needed after the internal audit finding on backups.');

        $this->task($company, $project, $today, PhaseKey::Planning, 'Collect three offers', 'teamlead', 3, TaskStatus::Done);
        $this->task($company, $project, $today, PhaseKey::Planning, 'Size the replication link', 'teamlead', 8, TaskStatus::InProgress);

        $this->reports($company, $project, $today, [
            [10, Rag::Green, Rag::Green, Rag::Green, 'Offers received.', 'Ask the sponsor to start execution.', null],
        ]);

        // The planning gate waits for the sponsor.
        DemoClock::at($today->subDays(2)->setTime(16, 0));
        $this->gates->request($project->refresh(), $company->user('projects'), 'Offers compared, recommending Northwind.');
    }

    private function awareness(DemoCompany $company, CarbonImmutable $today): void
    {
        $project = $this->start($company, $today, 88, 'Phishing awareness campaign', 'A first phishing simulation and short training for all staff.', ProjectKind::General, 'security', -10, ['employee_hr' => 'HR partner']);
        $this->budget->setBudget($project, '5000.00', 'EUR');

        $this->task($company, $project, $today, PhaseKey::Initiation, 'Agree the campaign with HR', 'employee_hr', 84, TaskStatus::Done);
        $this->advance($company, $project, $today, 82, 'Go ahead.');
        $this->task($company, $project, $today, PhaseKey::Planning, 'Write the simulation e-mails', 'projects', 70, TaskStatus::Done);
        $this->advance($company, $project, $today, 68, 'Plan agreed.');
        $this->task($company, $project, $today, PhaseKey::Execution, 'Run the simulation', 'projects', 40, TaskStatus::Done);
        $this->cost($company, $project, $today, 40, CostCategory::Services, 'Phishing simulation service', '3200.00', null);
        $this->reports($company, $project, $today, [[45, Rag::Green, Rag::Green, Rag::Green, 'Simulation ready.', 'Run it.', null]]);
        $this->advance($company, $project, $today, 30, 'Simulation done: 18% clicked, training sent.');
        $this->advance($company, $project, $today, 12, 'Lessons learned recorded; closed.');
    }

    /**
     * A new project with its team and planned phases, created by the project manager.
     *
     * @param  array<string, string>  $members  user key => role
     */
    private function start(DemoCompany $company, CarbonImmutable $today, int $daysAgo, string $name, string $description, ProjectKind $kind, string $sponsor, int $endsInDays, array $members): Project
    {
        DemoClock::at($today->subDays($daysAgo)->setTime(9, 30));
        $project = $this->projects->handle(null, [
            'name' => $name, 'description' => $description, 'kind' => $kind,
            'sponsor_id' => $company->user($sponsor)->id, 'manager_id' => $company->user('projects')->id,
            'planned_start' => $today->subDays($daysAgo)->toDateString(), 'planned_end' => $today->addDays($endsInDays)->toDateString(),
        ]);

        foreach ($members as $member => $role) {
            if ($member !== $sponsor) {
                $this->team->add($project, $company->user($member)->id, $role);
            }
        }

        $span = $daysAgo + $endsInDays;
        $start = $today->subDays($daysAgo);

        foreach ($project->phases()->get() as $phase) {
            // Share of the project's duration, in percent: where the phase starts and ends.
            [$from, $to] = match ($phase->key) {
                PhaseKey::Initiation => [0, 10],
                PhaseKey::Planning => [10, 30],
                PhaseKey::Execution => [30, 90],
                PhaseKey::Closure => [90, 100],
            };
            $this->team->planPhase($phase, $start->addDays(intdiv($span * $from, 100))->toDateString(), $start->addDays(max(0, intdiv($span * $to, 100) - ($to === 100 ? 0 : 1)))->toDateString());
        }

        return $project->refresh();
    }

    /**
     * The manager asks to move on; the sponsor approves the next day.
     */
    private function advance(DemoCompany $company, Project $project, CarbonImmutable $today, int $daysAgo, string $note): void
    {
        DemoClock::at($today->subDays($daysAgo + 1)->setTime(15, 0));
        $gate = $this->gates->request($project->refresh(), $company->user('projects'), $note);
        DemoClock::at($today->subDays($daysAgo)->setTime(10, 0));
        $this->gates->decide($gate->refresh(), $project->sponsor()->firstOrFail(), true, null);
    }

    private function task(DemoCompany $company, Project $project, CarbonImmutable $today, PhaseKey $phase, string $title, string $assignee, int $dueInDays, TaskStatus $status): void
    {
        $task = $this->tasks->save($project, null, [
            'phase_id' => $project->phases()->where('key', $phase->value)->sole()->id, 'title' => $title, 'description' => null,
            'assignee_id' => $company->user($assignee)->id, 'due_on' => $today->addDays($dueInDays)->toDateString(),
        ]);
        $this->tasks->move($task, $status);
    }

    private function deliverable(Project $project, PhaseKey $phase, string $title, bool $required): Deliverable
    {
        return $this->deliverables->add($project, ['phase_id' => $project->phases()->where('key', $phase->value)->sole()->id, 'title' => $title, 'required' => $required]);
    }

    private function cost(DemoCompany $company, Project $project, CarbonImmutable $today, int $daysAgo, CostCategory $category, string $description, string $amount, ?string $supplier): void
    {
        $this->budget->addCost($project, [
            'category' => $category, 'description' => $description, 'amount' => $amount, 'incurred_on' => $today->subDays($daysAgo)->toDateString(),
            'supplier_id' => $supplier !== null ? $company->suppliers[$supplier]->id : null, 'invoice_number' => null,
        ]);
    }

    /**
     * @param  list<array{0: int, 1: Rag, 2: Rag, 3: Rag, 4: string, 5: string, 6: ?string}>  $reports  days ago, overall, schedule, budget, achievements, next steps, issues
     */
    private function reports(DemoCompany $company, Project $project, CarbonImmutable $today, array $reports): void
    {
        foreach ($reports as [$daysAgo, $overall, $schedule, $budget, $achievements, $next, $issues]) {
            $day = $today->subDays($daysAgo);
            DemoClock::at($day->setTime(17, 0));
            $this->reports->handle($project, $company->user('projects'), [
                'overall' => $overall, 'schedule' => $schedule, 'budget' => $budget, 'scope' => Rag::Green,
                'achievements' => $achievements, 'next_steps' => $next, 'issues' => $issues,
            ], $day);
        }
    }
}
