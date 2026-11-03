<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Database\Seeders\DemoClock;
use Modules\Core\Database\Seeders\DemoCompany;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Risk\Application\Actions\AcceptRisk;
use Modules\Risk\Application\Actions\AssessRisk;
use Modules\Risk\Application\Actions\ManageRiskActions;
use Modules\Risk\Application\Actions\ManageRiskAssets;
use Modules\Risk\Application\Actions\SaveRisk;
use Modules\Risk\Application\Actions\SaveTreatment;
use Modules\Risk\Application\Catalog\ProvisionCatalog;
use Modules\Risk\Domain\Enums\AcceptanceStatus;
use Modules\Risk\Domain\Enums\ActionStatus;
use Modules\Risk\Domain\Enums\AssetType;
use Modules\Risk\Domain\Enums\RiskCategory;
use Modules\Risk\Domain\Enums\RiskStatus;
use Modules\Risk\Domain\Enums\Treatment;
use Modules\Risk\Domain\Models\CatalogEntry;

/**
 * The risk register: fifteen scenarios on the company's applications,
 * infrastructure, processes and suppliers, registered by the risk manager,
 * assessed with the catalog, treated with actions (some overdue), one
 * residual risk accepted and one acceptance waiting for a decision.
 * Keys are kept in DemoCompany::$refs ("risk.ransomware").
 */
final class RiskDemoSeeder extends Seeder
{
    /**
     * key => title, category, owner, assets, [likelihood, impact], threats, vulnerabilities, treatment, [residual L, I], actions, acceptance.
     *
     * Assets are [type, DemoCompany ref]; actions are [title, owner, due in days (negative: overdue), status].
     *
     * @var array<string, array{0: string, 1: RiskCategory, 2: string, 3: list<array{0: AssetType, 1: string}>, 4: array{0: int, 1: int}, 5: list<string>, 6: list<string>, 7: Treatment, 8: array{0: int, 1: int}, 9: list<array{0: string, 1: string, 2: int, 3: ActionStatus}>, 10: ?string}>
     */
    private const array RISKS = [
        'ransomware' => ['Ransomware encrypts the ERP and its backups', RiskCategory::InformationSecurity, 'security', [[AssetType::Application, 'applications.erp'], [AssetType::ConfigurationItem, 'cmdb.db_erp']], [3, 5], ['Ransomware and other malware'], ['No tested backup', 'Missing security updates'], Treatment::Mitigate, [2, 4], [['Immutable off-site backup copy', 'teamlead', -10, ActionStatus::InProgress], ['Monthly restore test of the ERP', 'agent2', 20, ActionStatus::Planned]], null],
        'phishing' => ['Phishing steals mailbox credentials', RiskCategory::InformationSecurity, 'security', [[AssetType::Application, 'applications.mail']], [4, 3], ['Phishing and social engineering'], ['Weak authentication or no MFA', 'Lack of awareness and training'], Treatment::Mitigate, [2, 3], [['Enforce MFA for every mailbox', 'agent', -30, ActionStatus::Done], ['Phishing awareness training for all staff', 'employee_hr', 30, ActionStatus::Planned]], null],
        'host_failure' => ['Hypervisor host failure stops the ERP', RiskCategory::Availability, 'teamlead', [[AssetType::ConfigurationItem, 'cmdb.host_1'], [AssetType::Process, 'bia.invoicing']], [2, 4], ['Hardware failure'], ['Single point of failure'], Treatment::Mitigate, [2, 2], [['Keep capacity on hv-02 for every ERP machine', 'teamlead', -5, ActionStatus::Done]], null],
        'core_switch' => ['Core switch out of support fails', RiskCategory::Availability, 'teamlead', [[AssetType::ConfigurationItem, 'cmdb.core_switch']], [3, 4], ['Hardware failure'], ['Unsupported software', 'Single point of failure'], Treatment::Mitigate, [2, 3], [['Replace the core switch (change CHG-000004)', 'agent2', 14, ActionStatus::InProgress]], null],
        'wifi' => ['Warehouse Wi-Fi outage stops picking', RiskCategory::Availability, 'employee_logistics', [[AssetType::ConfigurationItem, 'cmdb.wh_ap'], [AssetType::Process, 'bia.order_picking']], [3, 3], ['Telecommunication failure'], ['Single point of failure'], Treatment::Mitigate, [2, 2], [['Paper pick lists at every station', 'employee_logistics', -20, ActionStatus::Done]], null],
        'erp_vendor' => ['ERP vendor ends support of our version', RiskCategory::Supplier, 'applications', [[AssetType::Supplier, 'supplier.erp'], [AssetType::Application, 'applications.erp']], [3, 4], ['Supplier failure'], ['Dependency on a single supplier'], Treatment::Accept, [3, 4], [], 'pending'],
        'cloud_outage' => ['Cloud provider outage stops e-mail', RiskCategory::Supplier, 'teamlead', [[AssetType::Supplier, 'supplier.cloud'], [AssetType::Application, 'applications.mail']], [2, 5], ['Supplier failure', 'Telecommunication failure'], ['Dependency on a single supplier'], Treatment::Accept, [2, 5], [], 'approved'],
        'access_review' => ['Leavers keep access to the ERP', RiskCategory::InformationSecurity, 'applications', [[AssetType::Application, 'applications.erp']], [3, 3], ['Unauthorized access', 'Insider misuse'], ['Excessive access rights'], Treatment::Mitigate, [2, 2], [['Quarterly access review with HR', 'applications', -3, ActionStatus::Planned]], null],
        'payroll_error' => ['Payroll calculated wrongly after an update', RiskCategory::Operational, 'employee_hr', [[AssetType::Application, 'applications.payroll'], [AssetType::Process, 'bia.payroll']], [2, 3], ['Software failure', 'Human error'], ['Missing change control'], Treatment::Mitigate, [1, 3], [['Parallel run before every payroll engine update', 'employee_hr', 40, ActionStatus::Planned]], null],
        'key_person' => ['Only one person knows the WMS', RiskCategory::Operational, 'applications', [[AssetType::Application, 'applications.wms']], [3, 4], ['Key staff unavailable'], ['Undocumented procedures'], Treatment::Mitigate, [2, 3], [['Document the WMS runbook in Documentation', 'agent', -1, ActionStatus::InProgress]], null],
        'power' => ['Power cut in the server room', RiskCategory::Availability, 'teamlead', [[AssetType::Supplier, 'supplier.power']], [2, 4], ['Power failure'], ['Inadequate physical protection'], Treatment::Mitigate, [1, 3], [['Test the UPS batteries twice a year', 'teamlead', 60, ActionStatus::Planned]], null],
        'gdpr_hr' => ['HR data shared beyond what is needed', RiskCategory::Compliance, 'employee_hr', [[AssetType::Application, 'applications.hr_portal']], [2, 4], ['Data theft or leakage', 'Breach of law or contract'], ['Excessive access rights'], Treatment::Mitigate, [1, 3], [['Restrict HR portal reports to HR', 'applications', 10, ActionStatus::Planned]], null],
        'laptop_theft' => ['Laptop stolen with customer data', RiskCategory::InformationSecurity, 'security', [], [3, 3], ['Theft or loss of equipment'], ['Unencrypted data at rest'], Treatment::Mitigate, [3, 1], [['Disk encryption on every laptop', 'assets', -45, ActionStatus::Done]], null],
        'backup_untested' => ['File server backups never restored', RiskCategory::Availability, 'teamlead', [[AssetType::ConfigurationItem, 'cmdb.vm_file']], [3, 3], ['Hardware failure', 'Human error'], ['No tested backup'], Treatment::Mitigate, [2, 3], [['Quarterly file restore test', 'agent2', -15, ActionStatus::Planned]], null],
        'crm_project' => ['CRM project delivers late', RiskCategory::Project, 'projects', [[AssetType::Application, 'applications.crm']], [3, 2], ['Supplier failure'], [], Treatment::Accept, [3, 2], [], null],
    ];

    public function run(DemoCompany $company, OrganizationContext $context, ProvisionCatalog $catalog, SaveRisk $save, ManageRiskAssets $assets, AssessRisk $assess, SaveTreatment $treatment, ManageRiskActions $actions, AcceptRisk $accept): void
    {
        $context->runAs($company->organization, function () use ($company, $catalog, $save, $assets, $assess, $treatment, $actions, $accept): void {
            $catalog->handle();
            $manager = $company->user('risk');
            $today = DemoClock::today();
            $index = 0;

            foreach (self::RISKS as $key => [$title, $category, $ownerKey, $links, $inherent, $threats, $vulnerabilities, $option, $residual, $riskActions, $acceptance]) {
                $owner = $company->user($ownerKey);
                DemoClock::at(DemoClock::daysAgo(70 - 3 * $index++, '10:00'));
                $risk = $save->handle(null, ['title' => $title, 'description' => null, 'category' => $category, 'owner_id' => $owner->id, 'status' => RiskStatus::Open]);
                $company->refs['risk.'.$key] = $risk->id;

                foreach ($links as [$type, $ref]) {
                    $id = str_starts_with($ref, 'supplier.') ? $company->suppliers[substr($ref, 9)]->id : $company->ref($ref);
                    $assets->add($risk, $type, $id, $manager);
                }

                $entries = CatalogEntry::query()->whereIn('name', [...$threats, ...$vulnerabilities])->get()->map(fn (CatalogEntry $entry): string => $entry->id)->all();
                $assess->handle($risk->refresh(), $inherent[0], $inherent[1], array_values($entries), 'Backups, monitoring and access control as described in the policies.', $manager);
                $treatment->handle($risk->refresh(), $option, $option === Treatment::Accept ? 'Accepted while the cost of treatment exceeds the expected loss.' : 'See the actions.', $residual[0], $residual[1], $manager);

                foreach ($riskActions as [$action, $actionOwner, $dueInDays, $status]) {
                    $added = $actions->add($risk->refresh(), $action, $company->user($actionOwner)->id, $today->addDays($dueInDays), $manager);

                    if ($status !== ActionStatus::Planned) {
                        $actions->changeStatus($added, $status, $company->user($actionOwner));
                    }
                }

                if ($acceptance !== null) {
                    $request = $accept->request($risk->refresh(), 'The residual risk is within what the business agreed to carry.', $owner);

                    // Residuals from the approval threshold up wait for the risk manager.
                    if ($acceptance === 'approved' && $request->status === AcceptanceStatus::Pending) {
                        $accept->decide($request, true, 'Agreed until the next review.', $manager);
                    }
                }
            }

        });

        DemoClock::reset();
    }
}
