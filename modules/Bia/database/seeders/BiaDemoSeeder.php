<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Bia\Application\Actions\DecideAssessment;
use Modules\Bia\Application\Actions\ManageDependencies;
use Modules\Bia\Application\Actions\ProvisionDefaultMatrix;
use Modules\Bia\Application\Actions\SaveAssessment;
use Modules\Bia\Application\Actions\StartAssessment;
use Modules\Bia\Application\Actions\SubmitAssessment;
use Modules\Bia\Domain\Enums\DependencyKind;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Bia\Domain\Models\ImpactCategory;
use Modules\Bia\Domain\Models\TimeHorizon;
use Modules\Core\Database\Seeders\DemoClock;
use Modules\Core\Database\Seeders\DemoCompany;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * The demo company's business processes and their impact analysis: each
 * owner fills in and submits the assessment, the continuity coordinator
 * approves it. One process waits for approval, one was never assessed.
 * Keys are kept in DemoCompany::$refs ("bia.invoicing").
 */
final class BiaDemoSeeder extends Seeder
{
    /**
     * key => name, department, owner, unacceptable from (minutes), RTO, RPO, CMDB items, suppliers, stage.
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: int, 4: int, 5: int, 6: list<string>, 7: list<string>, 8: string}>
     */
    private const array PROCESSES = [
        'invoicing' => ['Invoicing', 'finance', 'employee_finance', 1440, 480, 60, ['erp', 'db_erp'], ['erp'], 'approved'],
        'payroll' => ['Payroll', 'hr', 'employee_hr', 4320, 1440, 240, ['erp'], ['erp'], 'approved'],
        'supplier_payments' => ['Supplier payments', 'finance', 'employee_finance', 4320, 1440, 240, ['erp'], [], 'approved'],
        'order_picking' => ['Order picking', 'logistics', 'employee_logistics', 240, 120, 15, ['wms', 'db_wms', 'wh_ap'], [], 'approved'],
        'shipping' => ['Shipping', 'logistics', 'employee_warehouse', 1440, 240, 60, ['wms'], ['network'], 'approved'],
        'customer_orders' => ['Customer orders', 'sales', 'employee_sales', 1440, 480, 60, ['erp', 'svc_email'], ['cloud'], 'approved'],
        'inventory_control' => ['Inventory control', 'logistics', 'employee_logistics', 10080, 2880, 1440, ['wms'], [], 'approved'],
        'monthly_reporting' => ['Monthly reporting', 'finance', 'employee_finance', 20160, 4320, 1440, ['erp'], [], 'approved'],
        'customer_support' => ['Customer support', 'sales', 'employee_sales', 4320, 1440, 240, ['svc_email'], ['cloud'], 'submitted'],
        'recruitment' => ['Recruitment', 'hr', 'employee_hr', 20160, 10080, 1440, [], [], 'none'],
    ];

    public function run(DemoCompany $company, OrganizationContext $context, ProvisionDefaultMatrix $matrix, StartAssessment $start, SaveAssessment $save, ManageDependencies $dependencies, SubmitAssessment $submit, DecideAssessment $decide): void
    {
        $context->runAs($company->organization, function () use ($company, $matrix, $start, $save, $dependencies, $submit, $decide): void {
            $matrix->handle();
            $coordinator = $company->user('continuity');
            $index = 0;

            foreach (self::PROCESSES as $key => [$name, $department, $ownerKey, $unacceptableFrom, $rto, $rpo, $items, $suppliers, $stage]) {
                $owner = $company->user($ownerKey);
                $process = BusinessProcess::query()->create([
                    'name' => $name,
                    'department_id' => $company->departments[$department]->id,
                    'owner_id' => $owner->id,
                    'description' => $name.' at Demo Logistics.',
                    'status' => 'active',
                ]);
                $company->refs['bia.'.$key] = $process->id;

                if ($stage === 'none') {
                    continue;
                }

                $daysAgo = 80 - 6 * $index++;
                DemoClock::at(DemoClock::daysAgo($daysAgo, '10:00'));
                $assessment = $start->handle($process, $owner);
                $save->handle($assessment, self::scores($unacceptableFrom), [
                    'rto_minutes' => $rto,
                    'rpo_minutes' => $rpo,
                    'mbco' => 'Urgent work only, by the core team.',
                    'workarounds' => 'Manual records on paper, entered once the systems are back.',
                    'minimum_staff' => 2,
                ]);

                foreach ($items as $item) {
                    $dependencies->linkItem($assessment, $company->ref('cmdb.'.$item), $owner);
                }

                foreach ($suppliers as $supplier) {
                    $dependencies->add($assessment, DependencyKind::Supplier, ['supplier_id' => $company->suppliers[$supplier]->id], $owner);
                }

                $dependencies->add($assessment, DependencyKind::Location, ['location_id' => $company->locations[$department === 'logistics' ? 'warehouse' : 'hq']->id], $owner);
                $submit->handle($assessment->refresh(), $owner);

                if ($stage === 'approved') {
                    DemoClock::at(DemoClock::daysAgo($daysAgo - 2, '15:00'));
                    $decide->handle($assessment->refresh(), $coordinator, true, null);
                }
            }
        });

        DemoClock::reset();
    }

    /**
     * Every cell of the matrix: low at first, unacceptable (4) from the given horizon on.
     *
     * @return array<string, string>
     */
    private static function scores(int $unacceptableFromMinutes): array
    {
        $scores = [];

        foreach (ImpactCategory::query()->get() as $category) {
            foreach (TimeHorizon::query()->get() as $horizon) {
                $scores[$category->id.'|'.$horizon->id] = $horizon->minutes >= $unacceptableFromMinutes ? '4' : '2';
            }
        }

        return $scores;
    }
}
