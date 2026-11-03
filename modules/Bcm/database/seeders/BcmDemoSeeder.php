<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Bcm\Application\Actions\DecidePlanVersion;
use Modules\Bcm\Application\Actions\ManagePlanContent;
use Modules\Bcm\Application\Actions\SavePlanVersion;
use Modules\Bcm\Application\Actions\StartPlanVersion;
use Modules\Bcm\Application\Actions\SubmitPlanVersion;
use Modules\Bcm\Application\Activations\ActivatePlan;
use Modules\Bcm\Application\Activations\RecordActivation;
use Modules\Bcm\Application\Exercises\CompleteExercise;
use Modules\Bcm\Application\Exercises\ManageFindings;
use Modules\Bcm\Application\Exercises\PlanExercise;
use Modules\Bcm\Application\Exercises\RecordExerciseResults;
use Modules\Bcm\Application\Exercises\StartExercise;
use Modules\Bcm\Domain\Enums\ExerciseOutcome;
use Modules\Bcm\Domain\Enums\FindingSeverity;
use Modules\Bcm\Domain\Enums\Scenario;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bcm\Domain\Models\PlanVersion;
use Modules\Core\Database\Seeders\DemoClock;
use Modules\Core\Database\Seeders\DemoCompany;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Continuity: the ERP recovery plan (DRP) and the warehouse continuity plan
 * (BCP), approved by the continuity coordinator; the head office plan still
 * a draft. The DRP was exercised (partly successful, with findings); the
 * warehouse plan was activated during the Wi-Fi outage. Keys are kept in
 * DemoCompany::$refs ("bcm.erp_drp").
 */
final class BcmDemoSeeder extends Seeder
{
    public function __construct(
        private StartPlanVersion $start,
        private SavePlanVersion $save,
        private ManagePlanContent $content,
        private SubmitPlanVersion $submit,
        private DecidePlanVersion $decide,
    ) {}

    public function run(DemoCompany $company, OrganizationContext $context, PlanExercise $planExercise, StartExercise $startExercise, RecordExerciseResults $results, ManageFindings $findings, CompleteExercise $complete, ActivatePlan $activate, RecordActivation $record): void
    {
        $context->runAs($company->organization, function () use ($company, $planExercise, $startExercise, $results, $findings, $complete, $activate, $record): void {
            $coordinator = $company->user('continuity');

            // The ERP recovery plan, kept by the infrastructure team lead.
            DemoClock::at(DemoClock::daysAgo(70, '10:00'));
            $drp = Plan::query()->create(['type' => 'drp', 'name' => 'ERP disaster recovery', 'description' => 'Bring the ERP back on the second host or from backup.', 'owner_id' => $company->user('teamlead')->id, 'deputy_id' => $company->user('agent2')->id, 'status' => 'active']);
            $company->refs['bcm.erp_drp'] = $drp->id;
            $version = $this->write($drp, $company->user('teamlead'), $company, [Scenario::ItOutage, Scenario::CyberAttack], ['invoicing', 'payroll', 'customer_orders'], ['erp', 'vm_erp_app', 'vm_erp_db', 'db_erp'], [
                ['activation', 'Declare the ERP outage and call the crisis team', 15, null],
                ['recovery', 'Start the ERP virtual machines on hv-02', 45, 'vm_erp_app'],
                ['recovery', 'Restore the ERP database from the last backup', 150, 'db_erp'],
                ['resumption', 'Check postings with Finance and reopen the ERP', 60, 'erp'],
                ['restoration', 'Move the virtual machines back once hv-01 is repaired', 60, null],
            ]);
            DemoClock::at(DemoClock::daysAgo(68, '16:00'));
            $this->decide->handle($version->refresh(), $coordinator, true, null);

            // The warehouse continuity plan, kept by Logistics.
            DemoClock::at(DemoClock::daysAgo(66, '10:00'));
            $bcp = Plan::query()->create(['type' => 'bcp', 'name' => 'Warehouse continuity', 'description' => 'Keep shipping from Cluj when systems or the site are unavailable.', 'owner_id' => $company->user('employee_logistics')->id, 'status' => 'active']);
            $company->refs['bcm.warehouse_bcp'] = $bcp->id;
            $version = $this->write($bcp, $company->user('employee_logistics'), $company, [Scenario::ItOutage, Scenario::SiteLoss, Scenario::StaffUnavailable], ['order_picking', 'shipping', 'inventory_control'], ['wms', 'wh_ap'], [
                ['activation', 'Warehouse manager declares the incident', 10, null],
                ['recovery', 'Switch to paper pick lists printed from the last WMS export', 30, null],
                ['resumption', 'Enter the paper movements in the WMS once it is back', 120, 'wms'],
            ]);
            DemoClock::at(DemoClock::daysAgo(64, '15:00'));
            $this->decide->handle($version->refresh(), $coordinator, true, null);

            // The head office plan is still being written.
            DemoClock::at(DemoClock::daysAgo(20, '10:00'));
            $office = Plan::query()->create(['type' => 'bcp', 'name' => 'Head office loss', 'description' => 'Work from home and the Cluj site if the Bucharest office is unavailable.', 'owner_id' => $company->user('employee_finance')->id, 'status' => 'active']);
            $draft = $this->start->handle($office, $company->user('employee_finance'));
            $this->save->handle($draft, ['objective' => 'Keep finance and sales running within one day.', 'scenarios' => [Scenario::SiteLoss->value]]);

            // A technical recovery test of the ERP plan, six weeks ago.
            $scheduledOn = DemoClock::daysAgo(42)->toDateString();
            DemoClock::at(DemoClock::daysAgo(45, '09:00'));
            $exercise = $planExercise->handle($drp->refresh(), [
                'type' => 'technical', 'scenario' => Scenario::ItOutage->value, 'title' => 'ERP restore test',
                'scheduled_on' => $scheduledOn, 'facilitator_id' => $coordinator->id, 'participants' => 'Infrastructure team, Finance key user',
            ]);
            DemoClock::at(DemoClock::daysAgo(42, '18:00'));
            $startExercise->handle($exercise);
            $exercise->refresh();
            $steps = [];

            foreach ($exercise->steps()->orderBy('position')->get() as $position => $step) {
                $steps[$step->id] = ['result' => $position === 2 ? 'issues' : 'passed', 'actual_minutes' => $position === 2 ? 210 : 40, 'notes' => $position === 2 ? 'The backup catalog had to be rebuilt first.' : null];
            }

            $items = [];

            foreach ($exercise->items()->get() as $item) {
                $items[$item->id] = ['recovered' => true, 'measured_minutes' => 300, 'notes' => null];
            }

            DemoClock::at(DemoClock::daysAgo(42, '23:30'));
            $results->handle($exercise, $steps, $items, $coordinator);
            $findings->add($exercise->refresh(), 'Restoring the ERP database took 3.5 hours instead of 2.5: the backup catalog was not replicated.', FindingSeverity::High, null, $coordinator);
            $findings->add($exercise->refresh(), 'The runbook does not say who informs Finance during the restore.', FindingSeverity::Low, null, $coordinator);
            $complete->handle($exercise->refresh(), ExerciseOutcome::Partial, 'The ERP was back in about 5 hours, within the 8-hour RTO, but the database restore ran late.', 'Replicate the backup catalog; add Finance to the call tree.', $coordinator);

            // A real activation: the warehouse Wi-Fi outage, three weeks ago.
            $activated = DemoClock::daysAgo(21, '07:40');
            DemoClock::at($activated->addMinutes(10));
            $activation = $activate->handle($bcp->refresh(), $activated, Scenario::ItOutage, 'Scanners cannot reach the WMS: warehouse Wi-Fi down.', false, $company->user('employee_logistics'));
            DemoClock::at($activated->addHours(5));
            $record->handle($activation, $activated->addHours(4)->addMinutes(20), 'Picking continued on paper; the access points were restarted and reconfigured.', 'Keep a printed pick list template at each station.', true, $company->user('employee_logistics'));
        });

        DemoClock::reset();
    }

    /**
     * Writes and submits the first version of a plan.
     *
     * @param  list<Scenario>  $scenarios
     * @param  list<string>  $processes
     * @param  list<string>  $items
     * @param  list<array{0: string, 1: string, 2: int, 3: ?string}>  $steps  phase, action, minutes, CMDB item
     */
    private function write(Plan $plan, User $owner, DemoCompany $company, array $scenarios, array $processes, array $items, array $steps): PlanVersion
    {
        $version = $this->start->handle($plan, $owner);
        $this->save->handle($version, [
            'objective' => 'Restore the service within its RTO with as little data loss as possible.',
            'scenarios' => array_map(fn (Scenario $scenario): string => $scenario->value, $scenarios),
            'activation_criteria' => 'The service is unavailable for more than 30 minutes, or is expected to be.',
            'activation_authority' => 'The IT team lead, or the department head.',
            'communication' => 'Phone call tree, then the status page and an e-mail to all staff.',
            'strategy' => 'Recover on the remaining infrastructure first; fall back to manual work in the meantime.',
            'assumptions' => 'Backups of the last 24 hours are available off-site.',
        ]);

        foreach ($processes as $process) {
            $this->content->addProcess($version, $company->ref('bia.'.$process), $owner);
        }

        foreach ($items as $item) {
            $this->content->linkItem($version, $company->ref('cmdb.'.$item), $owner);
        }

        $this->content->addMember($version, ['role' => 'Crisis lead', 'user_id' => $owner->id, 'responsibilities' => 'Declares the crisis and decides.'], $owner);
        $this->content->addMember($version, ['role' => 'IT recovery', 'user_id' => $company->user('agent2')->id, 'is_deputy' => false, 'responsibilities' => 'Runs the technical recovery.'], $owner);
        $this->content->addMember($version, ['role' => 'Supplier contact', 'contact_name' => 'Northwind Hardware support', 'contact_phone' => '+40 21 000 0001', 'responsibilities' => 'Hardware replacement within 4 hours.'], $owner);

        foreach ($steps as [$phase, $action, $minutes, $item]) {
            $this->content->saveStep($version, null, ['phase' => $phase, 'action' => $action, 'responsible_user_id' => $company->user('agent2')->id, 'estimated_minutes' => $minutes, 'configuration_item_id' => $item !== null ? $company->ref('cmdb.'.$item) : null], $owner);
        }

        $this->submit->handle($version->refresh(), $owner);

        return $version;
    }
}
