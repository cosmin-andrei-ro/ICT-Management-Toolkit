<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Database\Seeders\DemoCompany;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Pam\Application\Actions\ChangeSecret;
use Modules\Pam\Application\Actions\ManageAccessRequests;
use Modules\Pam\Application\PamVault;
use Modules\Pam\Domain\Enums\AccountType;
use Modules\Pam\Domain\Models\AccountGrant;
use Modules\Pam\Domain\Models\PrivilegedAccount;

/**
 * A few privileged accounts. Made-up credentials, stored only if the vault has a key.
 */
final class PamDemoSeeder extends Seeder
{
    public function run(DemoCompany $company, OrganizationContext $context, PamVault $vault, ChangeSecret $secrets, ManageAccessRequests $requests): void
    {
        $context->runAs($company->organization, function () use ($company, $vault, $secrets, $requests): void {
            $security = $company->user('security');
            $agent = $company->user('agent');

            $database = PrivilegedAccount::query()->create([
                'name' => 'ERP database owner', 'username' => 'erp_owner', 'type' => AccountType::Service->value, 'owner_id' => $security->id,
                'configuration_item_id' => $company->refs['cmdb.db_erp'] ?? null, 'application_id' => $company->refs['applications.erp'] ?? null,
                'description' => 'Used by the ERP application and by the database team for schema changes.', 'rotation_days' => 90, 'review_days' => 180,
            ]);
            $switch = PrivilegedAccount::query()->create([
                'name' => 'Core switch administrator', 'username' => 'admin', 'type' => AccountType::Administrator->value, 'owner_id' => $company->user('teamlead')->id,
                'requires_approval' => true, 'approver_id' => $security->id, 'access_minutes' => 30, 'rotation_days' => 30, 'review_days' => 90,
                'description' => 'Full control of the core network.', 'configuration_item_id' => $company->refs['cmdb.core_switch'] ?? null,
            ]);

            foreach ([$database, $switch] as $account) {
                AccountGrant::query()->create(['account_id' => $account->id, 'user_id' => $agent->id]);
                if ($vault->isConfigured()) {
                    $secrets->handle($account, 'demo-only-not-a-real-credential', $security);
                    $account->forceFill(['secret_changed_at' => now()->subDays(60)])->saveQuietly();
                }
            }

            $requests->ask($switch, $agent, 'Replacing a line card in the maintenance window.');
        });
    }
}
