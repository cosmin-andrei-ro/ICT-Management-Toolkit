<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Database\Seeders\DemoClock;
use Modules\Core\Database\Seeders\DemoCompany;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Documentation\Application\Actions\PublishPage;
use Modules\Documentation\Application\Actions\SavePage;
use Modules\Documentation\Application\Actions\SaveSpace;
use Modules\Documentation\Domain\Enums\SpaceVisibility;

/**
 * The company's own documentation, next to the application pages the
 * portfolio generates: IT runbooks, policies that go through approval
 * (one waiting), and help articles everyone can read - the portal suggests
 * them while an employee describes a problem. Keys are kept in
 * DemoCompany::$refs ("documentation.vpn").
 */
final class DocumentationDemoSeeder extends Seeder
{
    /**
     * key => title, body (Markdown).
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const array OPERATIONS = [
        'server_room' => ['Server room access', "Only the infrastructure team holds badges for the server room.\n\n1. Visitors sign the paper log at reception.\n2. A team member stays with them the whole time.\n3. The log is kept for one year."],
        'erp_restore' => ['ERP restore runbook', "The ERP database is backed up every night at 01:00 and every hour for the transaction log.\n\n## Restore\n\n1. Stop the ERP application service.\n2. Rebuild the backup catalog if it is missing.\n3. Restore the last full backup, then the logs up to the chosen moment.\n4. Ask Finance to check the last postings before reopening.\n\nSee the plan *ERP disaster recovery* in Continuity."],
        'wms_runbook' => ['WMS runbook', "Daily checks of the warehouse management system.\n\n- Scanner sessions: restart the WMS gateway when scanners stop syncing.\n- The nightly stock export lands on the file server at 05:00."],
        'patching' => ['Monthly patching', "Servers are patched in the second weekend of the month, through a standard change.\n\n| Group | Window |\n|---|---|\n| Test servers | Saturday 10:00 |\n| Production | Sunday 06:00 |"],
    ];

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const array POLICIES = [
        'security_policy' => ['Information security policy', "Demo Logistics protects the information it holds, and that of its customers, against loss, disclosure and unavailability.\n\n- Every system has a business and a technical owner.\n- Access is granted on need, and reviewed twice a year.\n- Incidents are reported to the service desk at once."],
        'backup_policy' => ['Backup policy', "- Critical systems: nightly full backup and hourly logs, kept 35 days.\n- Other systems: nightly backup, kept 14 days.\n- One copy off-site.\n- A restore test every quarter for each critical system."],
        'access_policy' => ['Access control policy', "Accounts are personal. Access is requested through the service desk and approved by the application owner.\n\nAccess of people who leave is removed on their last day."],
    ];

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const array HELP = [
        'vpn' => ['Connect to the VPN from home', "1. Open **Demo VPN** from the start menu.\n2. Sign in with your usual account and approve the request on your phone.\n3. Wait for the green shield, then open the ERP or the file shares.\n\nIf the VPN does not connect, restart your laptop and try again before calling the service desk."],
        'printer' => ['Add the office printer', "1. Open *Settings > Printers*.\n2. Choose **Add printer** and select *HQ-PRINT-01*.\n3. Print a test page.\n\nThe printer releases your job when you tap your badge on it."],
        'password' => ['Reset your password', "Use the *Forgot password* link on the sign-in page. A reset link arrives by e-mail and is valid for one hour.\n\nChoose a long passphrase: four or five words are easier to remember and harder to guess."],
        'mfa' => ['Set up the authenticator app on a new phone', "1. Install an authenticator app on the new phone.\n2. Ask the service desk to reset your second factor.\n3. Scan the QR code shown at your next sign-in."],
        'teams_meeting' => ['Join a video meeting from a meeting room', "Each meeting room has a screen with a camera. Tap **Join** on the room panel next to your meeting.\n\nIf there is no sound, check that the room speaker is selected on the panel."],
    ];

    public function run(DemoCompany $company, OrganizationContext $context, SaveSpace $spaces, SavePage $pages, PublishPage $publishing): void
    {
        $context->runAs($company->organization, function () use ($company, $spaces, $pages, $publishing): void {
            $teamLead = $company->user('teamlead');
            $security = $company->user('security');

            DemoClock::at(DemoClock::daysAgo(88, '09:00'));
            $operations = $spaces->handle(null, ['name' => 'IT operations', 'description' => 'Runbooks and procedures of the IT teams.', 'visibility' => SpaceVisibility::Staff, 'requires_approval' => false, 'review_months' => 12],
                [$teamLead->id], [], [$company->teams['desk']->id, $company->teams['infrastructure']->id, $company->teams['applications']->id], $teamLead);
            $policies = $spaces->handle(null, ['name' => 'Policies and procedures', 'description' => 'Approved policies of the information security management system.', 'visibility' => SpaceVisibility::Staff, 'requires_approval' => true, 'review_months' => 12],
                [$security->id, $company->user('compliance')->id], [], [], $security);
            $help = $spaces->handle(null, ['name' => 'Help for employees', 'description' => 'Answers to the most common questions, before you open a ticket.', 'visibility' => SpaceVisibility::Everyone, 'requires_approval' => false, 'review_months' => 6],
                [$company->user('agent')->id], [], [$company->teams['desk']->id], $teamLead);
            $company->refs['documentation.operations'] = $operations->id;
            $company->refs['documentation.policies'] = $policies->id;
            $company->refs['documentation.help'] = $help->id;

            $index = 0;

            foreach (self::OPERATIONS as $key => [$title, $body]) {
                DemoClock::at(DemoClock::daysAgo(86 - 5 * $index++, '11:00'));
                $company->refs['documentation.'.$key] = $pages->handle(null, $operations, null, $title, $body, null, null, $company->user($index % 2 === 0 ? 'agent2' : 'teamlead'))->id;
            }

            // The restore runbook was corrected after the ERP restore test.
            DemoClock::at(DemoClock::daysAgo(40, '10:00'));
            $runbook = $operations->pages()->findOrFail($company->ref('documentation.erp_restore'));
            $pages->handle($runbook, $operations, null, $runbook->title, $runbook->body."\n\nFinance is called by the crisis lead when the restore starts.", 'After the restore test', $runbook->version, $company->user('agent2'));

            // Policies are written by security and approved by the compliance manager; the access policy still waits.
            $index = 0;

            foreach (self::POLICIES as $key => [$title, $body]) {
                DemoClock::at(DemoClock::daysAgo(80 - 20 * $index++, '10:00'));
                $page = $pages->handle(null, $policies, null, $title, $body, 'First version', null, $security);
                $company->refs['documentation.'.$key] = $page->id;
                $publishing->submit($page, $security);

                if ($key !== 'access_policy') {
                    DemoClock::at(DemoClock::daysAgo(78 - 20 * ($index - 1), '15:00'));
                    $publishing->approve($page->refresh(), $company->user('compliance'));
                }
            }

            $index = 0;

            foreach (self::HELP as $key => [$title, $body]) {
                DemoClock::at(DemoClock::daysAgo(75 - 9 * $index++, '14:00'));
                $company->refs['documentation.'.$key] = $pages->handle(null, $help, null, $title, $body, null, null, $company->user('agent'))->id;
            }
        });

        DemoClock::reset();
    }
}
