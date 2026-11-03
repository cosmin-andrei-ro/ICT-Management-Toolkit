<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Modules\Applications\Domain\Models\Application;
use Modules\Audit\Domain\Enums\FindingSeverity;
use Modules\Audit\Domain\Enums\FindingStatus;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\Finding;
use Modules\Audit\Domain\Models\Framework;
use Modules\Bcm\Domain\Models\Activation;
use Modules\Bcm\Domain\Models\Exercise;
use Modules\Bcm\Domain\Models\Plan;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Cmdb\Domain\Models\Relationship;
use Modules\Core\Application\Exports\PersonalDataExport;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Dcim\Domain\Models\Cable;
use Modules\Dcim\Domain\Models\Rack;
use Modules\Dcim\Domain\Models\Subnet;
use Modules\Documentation\Domain\Enums\SpaceVisibility;
use Modules\Documentation\Domain\Models\Page;
use Modules\Documentation\Domain\Models\Space;
use Modules\Itam\Domain\Enums\AssetStatus;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\License;
use Modules\Projects\Domain\Enums\ProjectStatus;
use Modules\Projects\Domain\Models\GateRequest;
use Modules\Projects\Domain\Models\Project;
use Modules\Reporting\Domain\Models\MetricSnapshot;
use Modules\Reporting\Domain\Models\ReportSubscription;
use Modules\Risk\Domain\Enums\AcceptanceStatus;
use Modules\Risk\Domain\Models\Acceptance;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\RiskAction;
use Modules\Risk\Domain\Models\Suggestion;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Models\SlaTimer;
use Modules\Ticketing\Domain\Models\Ticket;

it('builds the demo company with an account per role and three months of history', function (): void {
    $this->seed(DatabaseSeeder::class);

    $organization = Organization::query()->where('slug', 'demo')->sole();
    app(OrganizationContext::class)->set($organization);

    // Every role can be tried with its own account, two-factor ready for staff.
    foreach (DefaultRole::cases() as $role) {
        expect(User::query()->role($role->value)->exists())->toBeTrue($role->value);
    }
    expect(User::query()->where('email', 'agent@example.com')->sole()->two_factor_confirmed_at)->not->toBeNull();

    // The service desk over 90 days, at every stage, with late SLAs.
    $tickets = Ticket::query()->get();
    expect($tickets->where('type', TicketType::Incident)->count() + $tickets->where('type', TicketType::ServiceRequest)->count())->toBe(60)
        ->and($tickets->min('created_at')->lt(now()->subDays(80)))->toBeTrue()
        ->and($tickets->where('status', TicketStatus::Closed)->count())->toBeGreaterThan(30)
        ->and($tickets->whereIn('status', [TicketStatus::New, TicketStatus::InProgress, TicketStatus::Pending])->count())->toBeGreaterThan(3)
        ->and($tickets->where('type', TicketType::Problem)->count())->toBe(2)
        ->and($tickets->where('type', TicketType::Change)->count())->toBe(4)
        ->and(SlaTimer::query()->whereNotNull('breached_at')->exists())->toBeTrue()
        ->and($tickets->every(fn (Ticket $ticket): bool => $ticket->created_at->lte(now())))->toBeTrue();

    // The estate, linked: assets are what CMDB items describe; approved applications add their own item.
    expect(ConfigurationItem::query()->count())->toBe(22)
        ->and(Relationship::query()->count())->toBe(20)
        ->and(Asset::query()->count())->toBeGreaterThan(30)
        ->and(app(ConfigurationItems::class)->linkedTo(Asset::query()->where('name', 'hv-01')->sole()))->toHaveCount(1)
        // Infrastructure is in service where it stands, with no holder.
        ->and(Asset::query()->where('name', 'hv-01')->sole())->status->toBe(AssetStatus::Deployed)->assigned_to_id->toBeNull()
        ->and(License::query()->where('expires_on', '<=', today()->addDays(60))->exists())->toBeTrue();

    // Resilience: analysed processes, documented applications, approved plans, exercised and activated.
    expect(BusinessProcess::query()->count())->toBe(10)
        ->and(Application::query()->count())->toBe(8)
        ->and(Application::query()->whereNotNull('configuration_item_id')->count())->toBe(5)
        ->and(Plan::query()->count())->toBe(3)
        ->and(Plan::query()->whereNotNull('approved_at')->count())->toBe(2)
        ->and(Exercise::query()->where('status', 'completed')->count())->toBe(1)
        ->and(Activation::query()->whereNotNull('closed_at')->count())->toBe(1);

    // Risk: an assessed register with overdue actions, one acceptance decided and one waiting.
    expect(Risk::query()->whereNotNull('assessed_at')->count())->toBe(15)
        ->and(RiskAction::query()->where('due_on', '<', today())->whereIn('status', ['planned', 'in_progress'])->exists())->toBeTrue()
        ->and(Acceptance::query()->where('status', AcceptanceStatus::Approved)->count())->toBe(1)
        ->and(Acceptance::query()->where('status', AcceptanceStatus::Pending)->count())->toBe(1)
        ->and(Suggestion::query()->exists())->toBeTrue();

    // Compliance: two frameworks, mapped and tested controls (some tests overdue), an internal audit.
    expect(Framework::query()->pluck('key')->sort()->values()->all())->toBe(['iso27001', 'nis2'])
        ->and(Control::query()->count())->toBe(12)
        ->and(Control::query()->where('next_test_on', '<', today())->exists())->toBeTrue()
        ->and(Finding::query()->where('severity', FindingSeverity::MajorNonconformity)->where('status', FindingStatus::Open)->exists())->toBeTrue()
        ->and(Finding::query()->where('status', FindingStatus::Closed)->count())->toBe(1);

    // Documentation: the generated application pages, runbooks, policies (one waiting) and help for everyone.
    expect(Space::query()->where('visibility', SpaceVisibility::Everyone)->count())->toBe(1)
        ->and(Page::query()->count())->toBe(17)
        ->and(Page::query()->whereNotNull('pending_version')->count())->toBe(1);

    // Infrastructure: racks with cabled equipment, A/B power with one device fed from one side, the address plan.
    expect(Rack::query()->count())->toBe(6)
        ->and(Cable::query()->count())->toBe(14)
        ->and(Subnet::query()->count())->toBe(4);

    // Projects at every stage: one over budget, one waiting at a gate, one closed.
    expect(Project::query()->count())->toBe(4)
        ->and(Project::query()->where('status', ProjectStatus::Closed)->count())->toBe(1)
        ->and(GateRequest::query()->where('status', 'pending')->count())->toBe(1);

    // Ninety days of indicators for the trends, and someone who receives the monthly report.
    expect(MetricSnapshot::query()->distinct()->count('taken_on'))->toBe(90)
        ->and(MetricSnapshot::query()->where('metric_key', 'dcim.single_fed')->where('taken_on', today()->toDateString())->value('value'))->toEqual(1)
        ->and(MetricSnapshot::query()->where('metric_key', 'projects.over_budget')->where('taken_on', today()->toDateString())->value('value'))->toEqual(1)
        ->and(ReportSubscription::query()->count())->toBe(1);

    // The export of a person's data reads every module: a busy team lead and an employee.
    foreach (['teamlead@example.com' => ['Organization', 'Service desk', 'CMDB', 'Continuity', 'Risks', 'Projects', 'Activity'], 'finance@example.com' => ['Service desk', 'Assets and licences', 'Applications', 'Business impact analysis']] as $email => $expected) {
        $export = new Export;
        $export->forceFill(['organization_id' => $organization->id, 'user_id' => User::query()->where('email', 'admin@example.com')->sole()->id, 'type' => PersonalDataExport::TYPE, 'format' => 'xlsx', 'status' => 'pending', 'parameters' => ['person_id' => User::query()->where('email', $email)->sole()->id]])->save();
        $sheets = app(PersonalDataExport::class)->sheets($export);
        $names = array_map(fn ($sheet): string => $sheet->name, $sheets);

        expect($names)->toContain(...$expected);
        foreach ($sheets as $sheet) {
            iterator_to_array((fn () => yield from $sheet->rows)(), false);
        }
    }
});

it('refuses to seed demo data outside local and testing', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    // Called directly: in production db:seed stops at its own confirmation prompt first.
    expect(fn () => app()->call([app(DatabaseSeeder::class), 'run']))->toThrow(RuntimeException::class, 'local or testing');
});
