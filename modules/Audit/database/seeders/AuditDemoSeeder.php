<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Audit\Application\Actions\AddFramework;
use Modules\Audit\Application\Actions\CloseFinding;
use Modules\Audit\Application\Actions\ManageEvidence;
use Modules\Audit\Application\Actions\ManageFindingActions;
use Modules\Audit\Application\Actions\ManageRequirements;
use Modules\Audit\Application\Actions\MapControl;
use Modules\Audit\Application\Actions\RecordControlTest;
use Modules\Audit\Application\Actions\SaveAudit;
use Modules\Audit\Application\Actions\SaveControl;
use Modules\Audit\Application\Actions\SaveFinding;
use Modules\Audit\Domain\Enums\ActionStatus;
use Modules\Audit\Domain\Enums\AuditKind;
use Modules\Audit\Domain\Enums\AuditStatus;
use Modules\Audit\Domain\Enums\ControlStatus;
use Modules\Audit\Domain\Enums\FindingSeverity;
use Modules\Audit\Domain\Enums\TestResult;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\Framework;
use Modules\Audit\Domain\Models\Requirement;
use Modules\Core\Database\Seeders\DemoClock;
use Modules\Core\Database\Seeders\DemoCompany;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Compliance: ISO 27001 and NIS2, the company's controls mapped to both,
 * evidence linked from the other modules, control tests (two overdue) and
 * an internal audit with findings - one major nonconformity still open.
 * Keys are kept in DemoCompany::$refs ("audit.backups").
 */
final class AuditDemoSeeder extends Seeder
{
    /**
     * key => name, owner, status, test every (months), ISO 27001 references, NIS2 references, evidence [type, ref], last test [days ago, result].
     *
     * @var array<string, array{0: string, 1: string, 2: ControlStatus, 3: ?int, 4: list<string>, 5: list<string>, 6: list<array{0: string, 1: string}>, 7: ?array{0: int, 1: TestResult}}>
     */
    private const array CONTROLS = [
        'access' => ['Access control and periodic access review', 'applications', ControlStatus::Partial, 6, ['5.15', '5.18'], ['Art. 21(2)(i)'], [['applications.application', 'applications.erp'], ['risk.risk', 'risk.access_review']], [200, TestResult::PartiallyEffective]],
        'mfa' => ['Multi-factor authentication', 'security', ControlStatus::Implemented, 12, ['5.17', '8.5'], ['Art. 21(2)(j)'], [['risk.risk', 'risk.phishing']], [60, TestResult::Effective]],
        'suppliers' => ['Supplier security and contracts', 'compliance', ControlStatus::Implemented, 12, ['5.19', '5.20', '5.22'], ['Art. 21(2)(d)'], [['core.contract', 'contract.erp_licence'], ['core.contract', 'contract.cloud_subscription']], [80, TestResult::Effective]],
        'incidents' => ['Incident management', 'teamlead', ControlStatus::Implemented, 6, ['5.24', '5.26'], ['Art. 21(2)(b)', 'Art. 23(4)(a)'], [], [50, TestResult::Effective]],
        'continuity' => ['Business continuity and disaster recovery', 'continuity', ControlStatus::Implemented, 12, ['5.29', '5.30'], ['Art. 21(2)(c)'], [['bcm.plan', 'bcm.erp_drp'], ['bcm.plan', 'bcm.warehouse_bcp'], ['bia.process', 'bia.invoicing']], [42, TestResult::PartiallyEffective]],
        'backups' => ['Backups and restore tests', 'teamlead', ControlStatus::Partial, 3, ['8.13'], ['Art. 21(2)(c)'], [['risk.risk', 'risk.backup_untested']], [130, TestResult::Ineffective]],
        'patching' => ['Vulnerability and patch management', 'security', ControlStatus::Partial, 3, ['8.8'], ['Art. 21(2)(e)'], [], [110, TestResult::PartiallyEffective]],
        'logging' => ['Logging and monitoring', 'agent2', ControlStatus::Implemented, 12, ['8.15', '8.16'], ['Art. 21(2)(b)'], [], null],
        'changes' => ['Change management', 'cab', ControlStatus::Implemented, 6, ['8.32'], ['Art. 21(2)(e)'], [], [30, TestResult::Effective]],
        'training' => ['Security awareness training', 'employee_hr', ControlStatus::Planned, 12, ['6.3'], ['Art. 21(2)(g)', 'Art. 20(2)'], [], null],
        'assets' => ['Asset inventory and ownership', 'assets', ControlStatus::Implemented, 12, ['5.9', '5.11'], ['Art. 21(2)(i)'], [], [70, TestResult::Effective]],
        'risk' => ['Information security risk assessment', 'risk', ControlStatus::Implemented, 12, ['5.1'], ['Art. 21(2)(a)', 'Art. 21(1)'], [['risk.risk', 'risk.ransomware']], [25, TestResult::Effective]],
    ];

    public function run(DemoCompany $company, OrganizationContext $context, AddFramework $frameworks, ManageRequirements $requirements, SaveControl $controls, MapControl $map, ManageEvidence $evidence, RecordControlTest $tests, SaveAudit $audits, SaveFinding $findings, ManageFindingActions $actions, CloseFinding $close): void
    {
        $context->runAs($company->organization, function () use ($company, $frameworks, $requirements, $controls, $map, $evidence, $tests, $audits, $findings, $actions, $close): void {
            $manager = $company->user('compliance');

            DemoClock::at(DemoClock::daysAgo(85, '09:00'));
            $iso = $frameworks->fromCatalog('iso27001');
            $nis2 = $frameworks->fromCatalog('nis2');
            $isoReferences = self::references($iso);
            $nis2References = self::references($nis2);

            // The statement of applicability: no outsourced development at Demo Logistics.
            $outsourced = Requirement::query()->where('framework_id', $iso->id)->where('reference', '8.30')->sole();
            $requirements->decide($outsourced, false, 'All development is done in house or bought as a product.', null, $manager);

            $saved = [];
            $index = 0;

            foreach (self::CONTROLS as $key => [$name, $owner, $status, $interval, $isoMapping, $nis2Mapping, $links]) {
                DemoClock::at(DemoClock::daysAgo(84 - $index++, '10:00'));
                $control = $controls->handle(null, ['name' => $name, 'description' => null, 'owner_id' => $company->user($owner)->id, 'status' => $status, 'implementation_notes' => null, 'test_interval_months' => $interval]);
                $company->refs['audit.'.$key] = $control->id;
                $saved[$key] = $control;

                foreach ([...self::ids($isoReferences, $isoMapping), ...self::ids($nis2References, $nis2Mapping)] as $requirementId) {
                    $map->add($control, $requirementId, $manager);
                }

                foreach ($links as [$type, $ref]) {
                    $id = str_starts_with($ref, 'contract.') ? $company->contracts[substr($ref, 9)]->id : $company->ref($ref);
                    $evidence->link($control, $type, $id, null, $manager);
                }
            }

            foreach ($saved as $key => $control) {
                [, $owner, , , , , , $lastTest] = self::CONTROLS[$key];

                if ($lastTest !== null) {
                    $testedAt = DemoClock::daysAgo($lastTest[0], '14:00');
                    DemoClock::at($testedAt);
                    $tests->handle($control->refresh(), $testedAt->startOfDay(), $lastTest[1], 'Sample of records reviewed with the control owner.', null, null, $company->user($owner === 'compliance' ? 'security' : 'compliance'));
                }
            }

            $this->internalAudit($company, $saved, $iso, $nis2, $isoReferences, $audits, $findings, $actions, $close);
        });

        DemoClock::reset();
    }

    /**
     * The yearly internal audit, led by the auditor: completed, with a major
     * nonconformity still open, a minor one closed after verification and an observation.
     *
     * @param  array<string, Control>  $controls
     * @param  array<string, string>  $isoReferences
     */
    private function internalAudit(DemoCompany $company, array $controls, Framework $iso, Framework $nis2, array $isoReferences, SaveAudit $audits, SaveFinding $findings, ManageFindingActions $actions, CloseFinding $close): void
    {
        $auditor = $company->user('auditor');
        $manager = $company->user('compliance');
        // Dates are taken before the clock moves: reading today resets it.
        $today = DemoClock::today();

        DemoClock::at(DemoClock::daysAgo(40, '09:00'));
        $audit = $audits->handle(null, [
            'title' => 'Internal ISMS audit 2026', 'kind' => AuditKind::Internal, 'scope' => 'IT operations at the Bucharest office and the Cluj warehouse.',
            'lead_auditor_id' => $auditor->id, 'audit_body' => null, 'starts_on' => $today->subDays(35), 'ends_on' => $today->subDays(30),
        ], [$iso->id, $nis2->id], $manager);
        DemoClock::at(DemoClock::daysAgo(35, '09:00'));
        $audits->changeStatus($audit, AuditStatus::InProgress, null);

        DemoClock::at(DemoClock::daysAgo(31, '16:00'));
        $major = $findings->handle(null, [
            'title' => 'Backups are not restore-tested', 'description' => 'No restore test of the file server in the last 12 months; the ERP test found the backup catalog missing.',
            'severity' => FindingSeverity::MajorNonconformity, 'owner_id' => $company->user('teamlead')->id, 'due_on' => $today->addDays(30),
            'control_id' => $controls['backups']->id, 'requirement_id' => $isoReferences['8.13'],
        ], $auditor, $audit);
        $close->rootCause($major, 'Restore tests were never scheduled; nobody owned them.');
        $actions->add($major, 'Schedule quarterly restore tests in the change calendar', $company->user('teamlead')->id, $today->addDays(5), $auditor);
        $actions->add($major, 'Replicate the backup catalog off-site', $company->user('agent2')->id, $today->subDays(2), $auditor);

        $minor = $findings->handle(null, [
            'title' => 'Access reviews not documented', 'description' => 'The ERP access review of spring was done but left no record.',
            'severity' => FindingSeverity::MinorNonconformity, 'owner_id' => $company->user('applications')->id, 'due_on' => $today->subDays(10),
            'control_id' => $controls['access']->id, 'requirement_id' => $isoReferences['5.18'],
        ], $auditor, $audit);
        $close->rootCause($minor, 'No template for recording the review.');
        $action = $actions->add($minor, 'Record access reviews as evidence on the control', $company->user('applications')->id, $today->subDays(15), $auditor);

        $findings->handle(null, [
            'title' => 'Awareness training not started', 'description' => 'The training programme is planned but no session took place yet.',
            'severity' => FindingSeverity::Observation, 'owner_id' => $company->user('employee_hr')->id, 'due_on' => null,
            'control_id' => $controls['training']->id, 'requirement_id' => null,
        ], $auditor, $audit);

        DemoClock::at(DemoClock::daysAgo(30, '17:00'));
        $audits->changeStatus($audit->refresh(), AuditStatus::Completed, 'The ISMS is largely in place. Backups need attention before the certification audit.');

        DemoClock::at(DemoClock::daysAgo(18, '11:00'));
        $actions->changeStatus($action, ActionStatus::Done, $company->user('applications'));
        $close->submit($minor->refresh(), $company->user('applications'));
        DemoClock::at(DemoClock::daysAgo(16, '10:00'));
        $close->verify($minor->refresh(), true, 'Review record attached to the control.', $manager);
    }

    /**
     * @param  array<string, string>  $byReference
     * @param  list<string>  $references
     * @return list<string>
     */
    private static function ids(array $byReference, array $references): array
    {
        return array_map(fn (string $reference): string => $byReference[$reference], $references);
    }

    /**
     * @return array<string, string> requirement id by reference
     */
    private static function references(Framework $framework): array
    {
        return Requirement::query()->where('framework_id', $framework->id)->get()
            ->mapWithKeys(fn (Requirement $requirement): array => [$requirement->reference => $requirement->id])->all();
    }
}
