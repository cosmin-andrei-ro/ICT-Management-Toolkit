<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Navigation\Navigation;
use Modules\Reporting\Application\MonthlyReport;
use Modules\Reporting\Application\Notifications\MonthlyReportReady;
use Modules\Reporting\Domain\Models\MetricSnapshot;
use Modules\Reporting\Domain\Models\ReportSubscription;
use Modules\Reporting\Http\Livewire\ManagementDashboard;

beforeEach(function (): void {
    app(SyncPermissions::class)->handle();
    $this->organization = Organization::factory()->create();
    app(OrganizationContext::class)->set($this->organization);
    $this->admin = managementMember($this->organization, DefaultRole::Administrator);
});

function managementMember(Organization $organization, DefaultRole $role, array $permissions = []): User
{
    $factory = User::factory()->memberOf($organization);
    $user = ($role === DefaultRole::Requester ? $factory : $factory->withTwoFactor())->create();
    app(OrganizationContext::class)->runAs($organization, function () use ($user, $role, $permissions): void {
        $user->assignRole($role->value);

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }
    });

    return User::query()->findOrFail($user->id);
}

it('shows every module\'s indicators to management, with links only where the person has access', function (): void {
    // A manager who sees the overview but none of the modules.
    $manager = managementMember($this->organization, DefaultRole::Requester, ['reporting.management.view']);

    $this->actingAs($manager)->get(route('reporting.management'))->assertOk()
        ->assertSee(__('ticketing::ui.metrics.sla_breached'))
        ->assertSee(__('audit::compliance.metrics.major_open'))
        ->assertDontSee(route('reporting.metrics.show', 'ticketing.open'));

    $this->actingAs($this->admin)->get(route('reporting.management'))->assertOk()
        ->assertSee(route('reporting.metrics.show', 'ticketing.open'));

    $labels = fn (User $user): array => collect(app(Navigation::class)->for($user))->flatten()->all();
    expect($labels($manager))->toContain(__('reporting::management.menu'));
});

it('keeps the overview and the report from people without the permission', function (): void {
    $agent = managementMember($this->organization, DefaultRole::Agent);

    $this->actingAs($agent)->get(route('reporting.management'))->assertForbidden();
    $this->actingAs($agent)->get(route('reporting.report.show'))->assertForbidden();
    $this->actingAs($agent)->get(route('reporting.report.pdf'))->assertForbidden();
});

it('reports each indicator at the end of the month against the month before', function (): void {
    $today = CarbonImmutable::parse('2026-11-15');
    MetricSnapshot::factory()->create(['metric_key' => 'ticketing.sla_breached', 'taken_on' => '2026-09-30', 'value' => 2]);
    MetricSnapshot::factory()->create(['metric_key' => 'ticketing.sla_breached', 'taken_on' => '2026-10-20', 'value' => 9]);
    MetricSnapshot::factory()->create(['metric_key' => 'ticketing.sla_breached', 'taken_on' => '2026-10-31', 'value' => 6]);
    MetricSnapshot::factory()->create(['metric_key' => 'ticketing.unassigned', 'taken_on' => '2026-10-31', 'value' => 0]);

    $report = app(MonthlyReport::class)->build(CarbonImmutable::parse('2026-10-01'), $today);
    $rows = collect($report['groups'])->flatMap(fn (array $group): array => $group['rows'])->keyBy(fn (array $row): string => $row['metric']->key);

    expect($rows['ticketing.sla_breached'])->toMatchArray(['closing' => 6.0, 'previous' => 2.0])
        ->and($rows['ticketing.sla_breached']['status']->value)->toBe('alert')
        ->and($rows['ticketing.unassigned']['status']->value)->toBe('good')
        ->and($rows['ticketing.open']['closing'])->toBeNull()
        ->and($report['statuses']['alert'])->toBe(1)
        ->and($report['end']->toDateString())->toBe('2026-10-31')
        ->and(MonthlyReport::month('2026-10', $today)?->toDateString())->toBe('2026-10-01')
        ->and(MonthlyReport::month('2025-10', $today))->toBeNull();
});

it('prints the report of a month, and sends people back when no PDF can be made', function (): void {
    $this->actingAs($this->admin);
    $month = now()->startOfMonth()->subMonthNoOverflow()->format('Y-m');

    $this->get(route('reporting.report.show'))->assertOk()
        ->assertSee(__('reporting::management.report.title', ['month' => now()->startOfMonth()->subMonthNoOverflow()->isoFormat('MMMM YYYY')]));
    $this->get(route('reporting.report.show', ['month' => '2019-01']))->assertNotFound();
    $this->get(route('reporting.report.show', ['month' => ['x']]))->assertNotFound();

    config(['core.pdf.gotenberg_url' => '']);
    $this->get(route('reporting.report.pdf', ['month' => $month]))->assertRedirect(route('reporting.report.show', ['month' => $month]));
});

it('lets administrators choose who receives the report, among people who may see it', function (): void {
    $manager = managementMember($this->organization, DefaultRole::Requester, ['reporting.management.view']);
    $agent = managementMember($this->organization, DefaultRole::Agent);

    $this->actingAs($this->admin);
    $page = Livewire::test(ManagementDashboard::class)
        ->set('subscriber', $manager->id)->call('subscribe')->assertHasNoErrors()
        ->set('subscriber', $agent->id)->call('subscribe')->assertHasErrors('subscriber')
        ->set('subscriber', $manager->id)->call('subscribe')->assertHasErrors('subscriber');

    $subscription = ReportSubscription::query()->sole();
    expect($subscription->user_id)->toBe($manager->id)
        ->and(AuditLog::query()->where('auditable_id', $subscription->id)->where('event', 'created')->exists())->toBeTrue();

    // Viewers who do not manage subscriptions cannot change them.
    $this->actingAs($manager);
    Livewire::test(ManagementDashboard::class)->assertDontSee(__('reporting::management.subscriptions.title'))
        ->call('unsubscribe', $subscription->id)->assertForbidden();

    $this->actingAs($this->admin);
    $page->call('unsubscribe', $subscription->id);
    expect(ReportSubscription::query()->exists())->toBeFalse();
});

it('emails the report of the month that ended to subscribers who may still see it', function (): void {
    Notification::fake();
    $manager = managementMember($this->organization, DefaultRole::Requester, ['reporting.management.view']);
    $former = managementMember($this->organization, DefaultRole::Requester, ['reporting.management.view']);
    ReportSubscription::factory()->create(['user_id' => $manager->id]);
    ReportSubscription::factory()->create(['user_id' => $former->id]);
    app(OrganizationContext::class)->runAs($this->organization, fn () => $former->revokePermissionTo('reporting.management.view'));
    MetricSnapshot::factory()->create(['metric_key' => 'ticketing.sla_breached', 'taken_on' => '2026-10-31', 'value' => 7]);

    // Another organization without subscribers.
    $other = Organization::factory()->create();
    managementMember($other, DefaultRole::Administrator);

    $this->artisan('reporting:monthly-report')->assertSuccessful();
    app(Modules\Reporting\Application\Actions\SendMonthlyReports::class)->handle(CarbonImmutable::parse('2026-11-01'));

    Notification::assertSentTo($manager, MonthlyReportReady::class, fn (MonthlyReportReady $mail): bool => $mail->month === 'October 2026'
        && $mail->statuses['alert'] === 1
        && $mail->pdf === null
        && str_contains($mail->url, 'month=2026-10'));
    Notification::assertNotSentTo($former, MonthlyReportReady::class);

    $mail = (new MonthlyReportReady('Sample', 'October 2026', ['alert' => 1, 'warning' => 2], 'https://example.test/report', base64_encode('%PDF-1.7'), 'management-report-2026-10.pdf'))->toMail($manager);
    expect($mail->rawAttachments)->toHaveCount(1)
        ->and($mail->introLines)->toContain('One indicator needs action now.', '2 indicators need attention.');
});

it('attaches the PDF when the PDF service runs', function (): void {
    Notification::fake();
    config(['core.pdf.gotenberg_url' => 'http://gotenberg:3000']);
    Http::fake(['gotenberg:3000/*' => Http::response('%PDF-1.7 report', 200, ['Content-Type' => 'application/pdf'])]);
    $manager = managementMember($this->organization, DefaultRole::Requester, ['reporting.management.view']);
    ReportSubscription::factory()->create(['user_id' => $manager->id]);

    app(Modules\Reporting\Application\Actions\SendMonthlyReports::class)->handle();

    Notification::assertSentTo($manager, MonthlyReportReady::class, fn (MonthlyReportReady $mail): bool => base64_decode((string) $mail->pdf) === '%PDF-1.7 report');
});

it('shows how each indicator changed in the last 30 days', function (): void {
    MetricSnapshot::factory()->create(['metric_key' => 'ticketing.open', 'taken_on' => today()->subDays(30), 'value' => 5]);
    MetricSnapshot::factory()->create(['metric_key' => 'ticketing.unassigned', 'taken_on' => today()->subDays(30), 'value' => 3]);

    $this->actingAs($this->admin);
    Livewire::test(ManagementDashboard::class)
        ->assertSeeInOrder([__('ticketing::ui.metrics.open'), '-5', __('ticketing::ui.metrics.unassigned'), '-3']);
});
