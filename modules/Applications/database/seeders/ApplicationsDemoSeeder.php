<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Applications\Application\Actions\DecideSheet;
use Modules\Applications\Application\Actions\SaveApplication;
use Modules\Applications\Application\Actions\SaveSheet;
use Modules\Applications\Application\Actions\StartSheet;
use Modules\Applications\Application\Actions\SubmitSheet;
use Modules\Applications\Domain\Enums\ApprovalRole;
use Modules\Core\Database\Seeders\DemoClock;
use Modules\Core\Database\Seeders\DemoCompany;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * The application portfolio: eight applications with business and IT
 * owners. Five have documentation approved by the business owner, the IT
 * side and security - which gives them their CMDB item and their page in
 * Documentation, as in real life - one waits for approval, one is a draft,
 * one is not documented yet. Keys are kept in DemoCompany::$refs ("applications.erp").
 */
final class ApplicationsDemoSeeder extends Seeder
{
    /**
     * key => code, name, business owner, CMDB item (existing), criticality, processes, stage, description.
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: ?string, 4: string, 5: list<string>, 6: string, 7: string}>
     */
    private const array APPLICATIONS = [
        'erp' => ['APP-ERP', 'ERP', 'employee_finance', 'erp', 'critical', ['invoicing', 'payroll', 'supplier_payments', 'monthly_reporting', 'customer_orders'], 'approved', 'Finance, purchasing and sales orders.'],
        'wms' => ['APP-WMS', 'Warehouse management system', 'employee_logistics', 'wms', 'critical', ['order_picking', 'shipping', 'inventory_control'], 'approved', 'Stock, picking and shipping in the Cluj warehouse.'],
        'hr_portal' => ['APP-HRP', 'HR portal', 'employee_hr', 'hr_portal', 'medium', ['recruitment'], 'approved', 'Leave requests, employee records and recruitment.'],
        'mail' => ['APP-MAIL', 'Mail and calendar', 'employee', null, 'high', ['customer_support', 'customer_orders'], 'approved', 'E-mail, calendars and shared mailboxes.'],
        'payroll' => ['APP-PAY', 'Payroll engine', 'employee_hr', null, 'high', ['payroll'], 'approved', 'Monthly salary calculation, linked to the ERP.'],
        'crm' => ['APP-CRM', 'CRM', 'employee_sales', null, 'high', ['customer_orders', 'customer_support'], 'submitted', 'Customer accounts, opportunities and support cases.'],
        'intranet' => ['APP-INT', 'Intranet', 'employee', null, 'low', [], 'draft', 'News and internal pages for employees.'],
        'bi' => ['APP-BI', 'Reporting and dashboards', 'employee_finance', null, 'medium', ['monthly_reporting'], 'none', 'Management reports built from the ERP.'],
    ];

    public function run(DemoCompany $company, OrganizationContext $context, SaveApplication $applications, StartSheet $start, SaveSheet $save, SubmitSheet $submit, DecideSheet $decide): void
    {
        $context->runAs($company->organization, function () use ($company, $applications, $start, $save, $submit, $decide): void {
            $technical = $company->user('agent');
            $index = 0;

            foreach (self::APPLICATIONS as $key => [$code, $name, $businessOwner, $item, $criticality, $processes, $stage, $description]) {
                $daysAgo = 75 - 7 * $index++;
                DemoClock::at(DemoClock::daysAgo($daysAgo, '09:30'));
                $application = $applications->handle(null, [
                    'code' => $code, 'name' => $name, 'department_id' => $company->departments['it']->id, 'department_email' => null,
                    'business_owner_id' => $company->user($businessOwner)->id, 'technical_owner_id' => $technical->id, 'deputy_id' => $company->user('agent2')->id,
                ]);
                $company->refs['applications.'.$key] = $application->id;

                // An application the CMDB already knows keeps its item instead of getting a second one.
                if ($item !== null) {
                    $application->forceFill(['configuration_item_id' => $company->ref('cmdb.'.$item)])->save();
                }

                if ($stage === 'none') {
                    continue;
                }

                $sheet = $start->handle($application->refresh(), $technical);
                $save->handle($sheet, [
                    '1_1' => ['description' => $description],
                    '1_3' => ['status' => 'in_production', 'criticality' => $criticality, 'exposure' => ['internal']],
                    '1_4' => ['environments' => [
                        ['environment' => 'production', 'url' => 'https://'.strtolower(substr($code, 4)).'.demo-logistics.example', 'purpose' => null, 'access' => null],
                        ['environment' => 'test', 'url' => 'https://'.strtolower(substr($code, 4)).'-test.demo-logistics.example', 'purpose' => null, 'access' => null],
                    ]],
                    '2_1' => ['classification' => $criticality === 'low' ? 'internal' : 'confidential', 'categories' => [], 'categories_other' => null],
                    '10_1' => ['processes' => array_map(fn (string $process): string => $company->ref('bia.'.$process), $processes), 'recovery_priority' => $criticality === 'critical' ? 'p1' : 'p2'],
                ], true, 'First documentation of '.$name);

                if ($stage === 'draft') {
                    continue;
                }

                $submit->handle($sheet->refresh(), $technical);

                if ($stage === 'approved') {
                    DemoClock::at(DemoClock::daysAgo($daysAgo - 1, '11:00'));
                    $decide->approve($sheet->refresh(), ApprovalRole::Business, null, $company->user($businessOwner));
                    $decide->approve($sheet->refresh(), ApprovalRole::Technical, null, $company->user('applications'));
                    DemoClock::at(DemoClock::daysAgo($daysAgo - 2, '14:00'));
                    $decide->approve($sheet->refresh(), ApprovalRole::Security, 'Reviewed: SSO, backups and logging in place.', $company->user('security'));
                    $company->refs['cmdb.app_'.$key] = (string) $application->refresh()->configuration_item_id;
                }
            }
        });

        DemoClock::reset();
    }
}
